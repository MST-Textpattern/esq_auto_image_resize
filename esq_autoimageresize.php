//<?php

if(@txpinterface == 'admin') {
	register_callback('esq_autoimageresize', 'image');
	register_callback('esq_autoimageresize_setup', 'plugin_lifecycle.esq_autoimageresize');
	register_callback('esq_autoimageresize_prefs', 'plugin_prefs.esq_autoimageresize');
	add_privs('plugin_prefs.esq_autoimageresize', '1,2');
}

function esq_autoimageresize($event, $step) {
	class esq_thumb extends txp_thumb {
		function write() {
			if (isset($this->m_ext) && wet_thumb::write(IMPATH.$this->m_id.$this->m_ext, IMPATH.$this->m_id.$this->m_ext)) {
				safe_update('txp_image', 'w = '.$this->width.', h = '.$this->height, 'id = '.$this->m_id);
				return true;
			} else {
				return false;
			}
		}
	}

	$esq_autoimageresize_prefs = get_pref('esq_autoimageresize_pref', false);
	if ($esq_autoimageresize_prefs != false) {
		$esq_autoimageresize_prefs = unserialize($esq_autoimageresize_prefs);
	} else {
		return;
	}

	$esq_autoimageresize_js = esq_autoimageresize_js($step, $esq_autoimageresize_prefs);
	if ($esq_autoimageresize_js != null) {
		echo '<script language="javascript" type="text/javascript">',
			n.'$(document).ready(function() {',
			n.join(n, $esq_autoimageresize_js),
			n.'})',
			n.'</script>';
	}
	esq_autoimageresize_resize($step, $esq_autoimageresize_prefs);
}

function esq_autoimageresize_js($step, $esq_autoimageresize_prefs) {
	switch ($step) {
		case 'image_insert':
		case 'image_replace':
			$out[] = '$(\'img#image-fullsize\').removeAttr(\'width\').removeAttr(\'height\').removeAttr(\'title\');';
		case 'image_edit':
			if ($esq_autoimageresize_prefs['hide_thumb_controls'] == 1) {
				$out[] = '$(\'input[value=thumbnail_create],input[value=thumbnail_insert]\').parents(\'tr\').hide();';
			}
			$out[] = '$(\'select#image-category\').parents(\'form\').append(\'<input type="hidden" name="previousCategory" value ="\' + $(\'select#image-category option[selected=selected]\').attr(\'value\') + \'" />\');';
			break;
	}
	return isset($out) ? $out : null;
}

function esq_autoimageresize_resize($step, $esq_autoimageresize_prefs) {
	$width = '';
	$height = '';

	switch ($step) {
		case 'image_insert':
		case 'image_replace':
			$now = safe_field('now()', 'txp_image', '1 = 1');
			$image_data = safe_row('*', 'txp_image', 'date = \''.$now.'\' limit 1');
			extract($image_data);
			if (count($image_data) == 0) {
				break;
			}
			if (
				($esq_autoimageresize_prefs['per_cat_resize'] == 1)
				&& ($category != '')
				&& isset($esq_autoimageresize_prefs[$category.'_width'])
				&& isset($esq_autoimageresize_prefs[$category.'_height'])
				&& isset($esq_autoimageresize_prefs[$category.'_crop'])
			) {
				$width = is_numeric($esq_autoimageresize_prefs[$category.'_width']) ? $esq_autoimageresize_prefs[$category.'_width'] : '';
				$height = is_numeric($esq_autoimageresize_prefs[$category.'_height']) ? $esq_autoimageresize_prefs[$category.'_height'] : '';
				$crop = $esq_autoimageresize_prefs[$category.'_crop'] == 1 ? true : false;
			} elseif (
				$esq_autoimageresize_prefs['per_cat_resize'] == 0
			) {
				$width = is_numeric($esq_autoimageresize_prefs['default_width']) ? $esq_autoimageresize_prefs['default_width'] : '';
				$height = is_numeric($esq_autoimageresize_prefs['default_height']) ? $esq_autoimageresize_prefs['default_height'] : '';
				$crop = $esq_autoimageresize_prefs['default_crop'] == 1 ? true : false;
			}
			break;
		case 'image_save':
			$image_data = safe_row('*', 'txp_image', 'id = \''.doSlash(gps('id')).'\' limit 1');
			extract($image_data);
			if (
				($esq_autoimageresize_prefs['per_cat_resize'] == 1)
				&& ($category != '')
				&& (gps('previousCategory') != $category)
				&& isset($esq_autoimageresize_prefs[$category.'_width'])
				&& isset($esq_autoimageresize_prefs[$category.'_height'])
				&& isset($esq_autoimageresize_prefs[$category.'_crop'])
			) {
				$width = is_numeric($esq_autoimageresize_prefs[$category.'_width']) ? $esq_autoimageresize_prefs[$category.'_width'] : '';
				$height = is_numeric($esq_autoimageresize_prefs[$category.'_height']) ? $esq_autoimageresize_prefs[$category.'_height'] : '';
				$crop = $esq_autoimageresize_prefs[$category.'_crop'] == 1 ? true : false;
			}
			break;
	}

	if (!(($width == '') && ($height == '')) && isset($id)) {
		$t = new esq_thumb($id);
		$t->crop = $crop;
		$t->hint = '0';
		$t->width = $width;
		$t->height = $height;
		$t->write();
	}
}

function esq_autoimageresize_prefs($event = '', $step = '') {
	$message = array(0 => array(), 1 => '');

	$imageCats = safe_rows('name, title', 'txp_category', 'type = \'image\' and parent != \'\'');

	$catNames = array();
	foreach($imageCats as $cat) {
		$catNames[] = $cat['name'];
	}
	array_unshift($catNames, 'default');
	foreach ($catNames as $catName) {
		foreach (array('width', 'height', 'crop') as $suffix) {
			$esq_autoimageresize_prefs_gps[] = $catName.'_'.$suffix;
		}
	}
	array_unshift($esq_autoimageresize_prefs_gps, 'per_cat_resize', 'hide_thumb_controls');

	if ($step == 'esq_autoimageresize_prefupdate') {
		$esq_autoimageresize_prefs = gpsa($esq_autoimageresize_prefs_gps);
		if (set_pref('esq_autoimageresize_pref', serialize($esq_autoimageresize_prefs), 'esq_air', PREF_HIDDEN, 'serialized_array') == false) {
			$message[0][] = 'Failed to update settings.';
			$message[1] = E_ERROR;
		} else {
			$message[0][] = 'Settings updated OK.';
		}
	} else {
		foreach (array_flip($esq_autoimageresize_prefs_gps) as $pref => $val) {
			$esq_autoimageresize_prefs_new[$pref] = '';
		}
		$esq_autoimageresize_prefs = get_pref('esq_autoimageresize_pref', false);
		if ($esq_autoimageresize_prefs == false) {
			$message[0][] = 'Failed to retrieve settings.';
			$message[1] = E_ERROR;
			$esq_autoimageresize_prefs = array_merge($esq_autoimageresize_prefs_new, unserialize(esq_autoimageresize_pref_defaults()));
		} else {
			$esq_autoimageresize_prefs = array_merge($esq_autoimageresize_prefs_new, unserialize($esq_autoimageresize_prefs));
		}
	}

	global $path_to_site, $img_dir;
	include(txpath.'/include/txp_image.php');
	if (function_exists('gd_info')) {
		foreach (array('.gif' => 'GIF', '.png' => 'PNG', '.jpg' => 'JPG') as $ext => $type) {
			if (!check_gd($ext)) {
				$gd_fail[] = $type;
			}
		}
		if (isset($gd_fail)) {
			$message[0][] = 'GD not enabled for '.join(', ', $gd_fail).'.';
			$message[1] = E_ERROR;
		}
	} else {
		$message[0][] = 'GD not installed.';
		$message[1] = E_ERROR;
	}

	$message[0] = join(' ', $message[0]);
	esq_autoimageresize_prefs_ouput($imageCats, $esq_autoimageresize_prefs, $message);
}

function esq_autoimageresize_prefs_ouput($imageCats, $esq_autoimageresize_prefs, $message) {
	pagetop('esq_autoimageresize Options', $message);

	foreach(array_merge(array('default' => array('title' => '', 'name' => 'default')), $imageCats) as $catType => $cat) {
		$class = $catType === 'default' ? 'defaultOptions' : 'categoryOptions';
		$esq_autoimageresize_prefs_form[] = tr(
			tda(strong($cat['title'].' Resize Options'), ' colspan="2"')
		, ' class="'.$class.'"')
		.tr(
			tda('Width:')
			.tda(fInput('text', $cat['name'].'_width', $esq_autoimageresize_prefs[$cat['name'].'_width']))
		, ' class="'.$class.'"')
		.tr(
			tda('Height:')
			.tda(fInput('text', $cat['name'].'_height', $esq_autoimageresize_prefs[$cat['name'].'_height']))
		, ' class="'.$class.'"')
		.tr(
			tda('Crop?')
			.tda(yesnoRadio($cat['name'].'_crop', $esq_autoimageresize_prefs[$cat['name'].'_crop']))
		, ' class="'.$class.'"');
	}
	$esq_autoimageresize_prefs_form = isset($esq_autoimageresize_prefs_form) ? join(n, $esq_autoimageresize_prefs_form) : '';

	echo form(
		startTable('list')
		.tr(
			tda(strong('esq_autoimageresize Options'), ' colspan="2"')
		)
		.tr(
			tda('Use per-category resize settings?')
			.tda(yesnoRadio('per_cat_resize', $esq_autoimageresize_prefs['per_cat_resize']))
		)
		.tr(
			tda('Hide thumbnail create and upload controls?')
			.tda(yesnoRadio('hide_thumb_controls', $esq_autoimageresize_prefs['hide_thumb_controls']))
		)
		.$esq_autoimageresize_prefs_form
		.tr(
			tda(fInput('submit', 'submit', 'Update', 'publish'), ' colspan="2" style="text-align: right;"')
		)
		.endTable()
		.eInput('plugin_prefs.esq_autoimageresize')
		.sInput('esq_autoimageresize_prefupdate')
	);

	echo <<<EOF
<script language="javascript" type="text/javascript">
	$(document).ready(function() {
		$('input[name=per_cat_resize]').click(function() {
			per_cat_resize_toggle($(this).attr('value'));
		});
		per_cat_resize_toggle($('input[name=per_cat_resize]:checked').attr('value'));
		if (!$('tr.categoryOptions').length) {
			$('input#per_cat_resize-1').removeAttr('checked').attr('disabled', 'disabled');
			$('input#per_cat_resize-0').attr('checked', 'checked');
			per_cat_resize_toggle(0);
		}
	})
	function per_cat_resize_toggle(val) {
		$('tr.defaultOptions, tr.categoryOptions').hide();
		if (val == '0') {
			$('tr.defaultOptions').show();
		} else if (val == '1') {
			$('tr.categoryOptions').show();
		}
	}
</script>
EOF;
}

function esq_autoimageresize_pref_defaults() {
	return 'a:5:{s:14:"per_cat_resize";s:1:"0";s:19:"hide_thumb_controls";s:1:"0";s:13:"default_width";s:0:"";s:14:"default_height";s:0:"";s:12:"default_crop";s:1:"0";}';
}

function esq_autoimageresize_setup($event, $step) {
	switch ($step) {
		case 'installed':
			if (set_pref('esq_autoimageresize_pref', esq_autoimageresize_pref_defaults(), 'esq_air', PREF_HIDDEN, 'serialized_array') == false) {
				return array('<strong>esq_autoimageresize</strong> pref setup failed.', E_ERROR);
			}
			break;
		case 'deleted':
			if (safe_delete('txp_prefs', 'name = \'esq_autoimageresize_pref\'') == false) {
				return array('<strong>esq_autoimageresize</strong> pref delete failed.', E_ERROR);
			}
			break;
	}
}