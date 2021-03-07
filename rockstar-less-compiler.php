<?php
/**
* Plugin Name: Rockstar Developer - LESS Compiler
* Plugin URI: https://www.rockstardeveloper.eu/
* Description: A speed optimized LESS compiler for Wordpress
* Version: 1.0
* Author: Thomas Bruninx
* Author URI: https://www.rockstardeveloper.eu/
**/

require_once("lessphp-master/lessc.inc.php");

// Function which recompiles all sources on page load
/****************************************************/ 
function rdev_less_recompile_onpageload() {
	$auto_compile_enabled = get_option('rdev_less_autocompile', false);

	if ($auto_compile_enabled) {
		rdev_less_compile_sources();	
	}

}
add_action( 'admin_head', 'rdev_less_recompile_onpageload' );
add_action( 'wp_head', 'rdev_less_recompile_onpageload' );

// Function which enqueues compiled files (Frontend only)
/****************************************************/ 
function rdev_less_enqueue_onpageload() {
	$sources = get_option('rdev_less_sources', array());
	if ($sources) {
		foreach($sources as $key => $src) {
			if ($src['enabled'] && !empty($src['compiled'])) {
				wp_enqueue_style($key, $src['compiled'], array(), false, 'all');
			}
		}
	}
}
add_action( 'wp_enqueue_scripts', 'rdev_less_enqueue_onpageload' );

// Main compilation function
/****************************************************/ 
function rdev_less_compile_sources($force = false) {
	$errors = array();

	$less = new lessc;
	$less->setFormatter("compressed");

	$sources = get_option('rdev_less_sources', array());
	$vars = get_option('rdev_less_variables', array());

	// Check if cache folder exists
	$cache_folder = wp_upload_dir()['basedir'].'/rdev_less_cache';
	$cache_folder_url = wp_upload_dir()['baseurl'].'/rdev_less_cache';
	$exists = is_dir($cache_folder);
	if (!$exists) {
		try {	
			mkdir($cache_folder);	
		} catch (Exception $e) {
			array_push($errors, array('file' => $cache_folder, 'type' => 'FILE', 'error' => $e->getMessage()));
		}				
	}
	
	// Set variables
	$vars['wp_template_directory_uri'] = "'".get_template_directory_uri()."'";
	$less->setVariables($vars);
	
	// Compile sources if there are any and if they exist
	if ($sources) {
		foreach($sources as $key => $src){
			if ($src['enabled']) {
				$in_file = $src['absolute'] ? $src['file'] : $_SERVER['DOCUMENT_ROOT'].'/'.$src['file'];
				$out_file = $cache_folder.'/'.$key.'.css';
				$out_file_url = $cache_folder_url.'/'.$key.'.css';
		
				if (file_exists($in_file)) {
		
					try {		
						if ($force) {
						 	$less->compileFile($in_file, $out_file);
						} else {
							$less->checkedCompile($in_file, $out_file);
						}
						$sources[$key]['compiled'] = $out_file_url;
					} catch (Exception $e) {
						array_push($errors, array('file' => $src['file'], 'type' => 'COMPILE', 'error' => $e->getMessage()));
						$sources[$key]['compiled'] = '';
					}
		
				} else {
					array_push($errors, array('file' => $src['file'], 'type' => 'FILE', 'error' => 'File does not exist'));
				}
		
			} else {
				array_push($errors, array('file' => $src['file'], 'type' => 'SKIPPED', 'error' => ''));
			}
		}
	}
	
	// Keep errors in error log
	update_option('rdev_less_errorlog', $errors);
	update_option('rdev_less_sources', $sources);
	
}

// Function to check if array contains key
/****************************************************/ 
function rdev_less_find_key_value($array, $key, $val) {
    foreach ($array as $item) {
        if (is_array($item) && rdev_less_find_key_value($item, $key, $val)) return true;
        if (isset($item[$key]) && $item[$key] == $val) return true;
    }
    return false;
}


// Function to clear cache
/****************************************************/ 
function rdev_less_clear_cache() {
	$cache_folder = wp_upload_dir()['basedir'].'/rdev_less_cache';
	$exists = is_dir($cache_folder);
	if ($exists) {

		// Delete all files
		$files = glob($cache_folder.'/*'); 
		foreach($files as $file){ 
			if(is_file($file)) {
				unlink($file); 
			}
		}

		// Clear all compiled entries of source files
		if ($sources) {
			foreach($sources as $key => $src){
				$sources[$key]['compiled'] = '';
			}
		}	

	}
}

// Register WP adminpage
/****************************************************/ 
function rdev_less_adminpage_register() {
  add_menu_page(
    'LESS Compiler',
    'LESS Compiler',
    'manage_options',
    'rdev-less',
    'rdev_less_adminpage',
    'dashicons-admin-generic',
    1000
  );
}
add_action( 'admin_menu', 'rdev_less_adminpage_register' );

// WP adminpage
/****************************************************/ 
function rdev_less_adminpage() {
	$sources = get_option('rdev_less_sources', array());
	$vars = get_option('rdev_less_variables', array());

	// Process actions
	$action = isset($_POST['rdev_action']) ? $_POST['rdev_action'] : '';
	
	switch ($action) {
		case 'recompile':
			rdev_less_compile_sources(true);
			break;

		case 'toggleautocompile':
			$auto_compile_enabled = !get_option('rdev_less_autocompile', false);
			update_option('rdev_less_autocompile', $auto_compile_enabled);
			break;

		case 'togglesource':
			$auto_compile_enabled = !get_option('rdev_less_autocompile', false);
			$fileid = isset($_POST['rdev_fileid']) ? $_POST['rdev_fileid'] : '';	
			$sources[$fileid]['enabled'] = !$sources[$fileid]['enabled'];
			update_option('rdev_less_sources', $sources);		
			if ($auto_compile_enabled) { rdev_less_compile_sources(); }
			break;
			
		case 'addsource':
			$file = isset($_POST['rdev_file']) ? $_POST['rdev_file'] : '';
			$absolutepath = isset($_POST['rdev_absolute']) ? 1 : 0;
			$sources[uniqid()] = array('file' => $file, 'absolute' => $absolutepath, 'enabled' => 0);
			update_option('rdev_less_sources', $sources);		
			break;
			
		case 'removesource':
			$fileid = isset($_POST['rdev_fileid']) ? $_POST['rdev_fileid'] : '';		
			unset($sources[$fileid]);
			update_option('rdev_less_sources', $sources);
			break;

		case 'addvar':
			$auto_compile_enabled = get_option('rdev_less_autocompile', false);
			$varname = isset($_POST['rdev_varname']) ? $_POST['rdev_varname'] : '';
			$varvalue = isset($_POST['rdev_varvalue']) ? $_POST['rdev_varvalue'] : '';
			$vars[$varname] = $varvalue;
			update_option('rdev_less_variables', $vars);	
			if ($auto_compile_enabled) { rdev_less_compile_sources(true); }
			break;

		case 'removevar':
			$varname = isset($_POST['rdev_varname']) ? $_POST['rdev_varname'] : '';
			unset($vars[$varname]);
			update_option('rdev_less_variables', $vars);
			break;

		case 'clearcache':
			rdev_less_clear_cache();
			break;
	}

	wp_register_script( 'rdev_less_backendjs', plugins_url('/script.js', __FILE__), array('jquery'));
	wp_enqueue_script( 'rdev_less_backendjs' );

	$errors = get_option('rdev_less_errorlog', array());
	
	// Actual adminpage
?>

	<div class="rdev_less_adminpage">
	
		<div class="rdev_less_sources">

			<h3>Source files</h3>

			<br>

			<form method="post">
				<?php
					if($sources){
						foreach($sources as $key => $src){
				?>			
							<label class="rdev-less-source" id="<?php echo $key; ?>-source">
								<?php $file = $src['absolute'] ? $src['file'] : $_SERVER['DOCUMENT_ROOT']."/".$src['file']; ?>

								<?php echo $file; ?>
								<span class="remove-source dashicons dashicons-trash" data-fileid="<?php echo $key; ?>"></span>
								<span class="toggle-source dashicons <?php echo $src['enabled'] ? 'dashicons-yes' : 'dashicons-no'; ?>" data-fileid="<?php echo $key; ?>"></span>
								
								<?php 
									if (!file_exists($file) || rdev_less_find_key_value($errors, 'file', $file)) {
										echo '<span class="dashicons dashicons-info"></span>';
									}
								?>
							</label>
							<br>
				<?php
						}
					}
				?>

				<input type="hidden" name="rdev_fileid" value="">
				<input type="hidden" name="rdev_action" value="">
			</form>
			
		</div>
		
		<hr>
		
		<div class="rdev_less_addsource">
		
			<h3>Add source file</h3>

			<br>

			<form method="post">
				<span class="relative-prefix"><?php echo $_SERVER['DOCUMENT_ROOT'].'/'; ?></span> <input type="text" placeholder="path" name="rdev_file" style="width: 400px;">
				Absolute <input type="checkbox" name="rdev_absolute" id="rdev_less_addsource_absolute_toggle">
				<input type="submit" value="+ Add source">
				<input type="hidden" name="rdev_action" value="addsource">
			</form>
		
		</div>
		
		<hr>
		
		<div class="rdev_less_var">

			<h3>LESS variables</h3>

			<br>

			<form method="post">
				<?php
					if($vars){
						foreach($vars as $key => $value){
				?>			
							<label class="rdev-less-var" id="<?php echo $key; ?>-var">
								<?php echo $key.' = '.$value; ?>
								<span class="remove-var dashicons dashicons-trash" data-varname="<?php echo $key; ?>"></span>
								<span class="edit-var dashicons dashicons-edit" data-varname="<?php echo $key; ?>" data-varvalue="<?php echo $value; ?>"></span>
							</label>
							<br>
				<?php
						}
					}
				?>

				<input type="hidden" name="rdev_varname" value="">
				<input type="hidden" name="rdev_action" value="">
			</form>
		
		</div>

		<hr>

		<div class="rdev_less_var">

			<h3>Add LESS variable</h3>

			<br>

			<form method="post" id="variable-editor">
			  <input type="text" placeholder="variable name" name="rdev_varname" style="width: 100px;">
				<input type="text" placeholder="variable value" name="rdev_varvalue" style="width: 400px;">
				<input type="submit" value="+ Add variable">
				<input type="hidden" name="rdev_action" value="addvar">
			</form>
		
		</div>
		
		<hr>
		
		<div class="rdev_less_errors">

			<h3>Errors on last compile run</h3>

			<br>
		
			<div class="less-errors">
				<?php if (!empty($errors)) { ?>
				<table>
					<thead>
						<th>File</th>
						<th>Type</th>
						<th>Error</th>
					</thead>
					<tbody>
						<?php foreach($errors as $key => $error){ ?>
						
							<tr>
								<td><?php echo $error['file']; ?></td>
								<td><?php echo $error['type']; ?></td>
								<td><?php echo $error['error']; ?></td>
							</tr>
						
						<?php } ?>					
					</tbody>
				</table>
				<?php } else { ?>
					<b>No errors on last compile run</b>
				<?php } ?>
			</div>
			
		</div>

		<hr>
	
		<div class="rdev_less_compilation">

			<h3>Compilation</h3>

			<br>

			<form method="post">
				<input type="submit" value="Recompile">
				<input type="hidden" name="rdev_action" value="recompile">
			</form>
			
			<br>

			<form method="post">
				<?php $auto_compile_enabled = get_option('rdev_less_autocompile', false); ?>
				<input type="submit" value="<?php echo $auto_compile_enabled ? "Disable automatic compilation" : "Enable automatic compilation" ; ?>">
				<input type="hidden" name="rdev_action" value="toggleautocompile">
			</form>

			<br>

			<form method="post">
				<input type="submit" value="Clear cache">
				<input type="hidden" name="rdev_action" value="clearcache">
			</form>

		</div>

	</div>
	
<?php
}
?>