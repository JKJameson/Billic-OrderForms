<?php
class OrderForms {
	public $settings = array(
		'name' => 'Order Forms',
		'admin_menu_category' => 'Ordering',
		'admin_menu_name' => 'Order Forms',
		'admin_menu_icon' => '<i class="icon-list"></i>',
		'description' => 'Build the order forms for your plans to use during the order process.',
	);
	function admin_area() {
		global $billic, $db;
		if (is_array($_FILES) && !empty($_FILES)) {
			foreach ($_FILES as $id => $file) {
				$id = explode('_', $id);
				$id = $id[1];
				$extension = explode('.', $file['name']);
				$extension = $extension[count($extension) - 1];
				if ($extension != 'png' && $extension != 'gif' && $extension != 'jpg' && $extension != 'jpeg') {
					$billic->error('Unsupported file type: ' . $extension);
					continue;
				}
				if (!file_exists('i/orderformitems/') || !is_dir('i/orderformitems/')) {
					$billic->error('Please create the folder i/orderformitems/ to upload an image');
					continue;
				}
				if (!move_uploaded_file($file['tmp_name'], 'i/orderformitems/' . $id . '.' . $extension)) {
					$billic->error('There was an error while moving the uploaded file');
					continue;
				}
				$db->q('UPDATE `orderformitems` SET `img` = ? WHERE `id` = ?', $id . '.' . $extension, $id);
				$billic->status = 'uploaded';
			}
		}
		$input_types = array(
			'text',
			'dropdown',
			'checkbox',
			'textarea',
			'password',
			'slider'
		);
		$requirement_types = array(
			'',
			'required',
			'alphanumeric',
			'email'
		);
		if (isset($_GET['Edit'])) {
			$orderform = $db->q('SELECT * FROM `orderforms` WHERE `name` = ?', urldecode($_GET['Edit']));
			$orderform = $orderform[0];
			if (empty($orderform)) {
				err('Order form does not exist');
			}
			$imported = false;
			if (strlen($orderform['name']) == 128) {
				$imported = true;
				$orderform_realname = $orderform['name'];
				$orderform['name'] = '[Imported]';
			}
			$title = 'Order Form ' . $orderform['name'];
			$billic->set_title($title);
			echo '<h1>' . $title . '</h1>';
			if (isset($_POST['addfield'])) {
				$db->insert('orderformitems', array(
					'name' => $_POST['label'],
					'type' => $_POST['type'],
					'parent' => $orderform['id'],
					'order' => time() ,
				));
				$billic->status = 'added';
			}
			if (isset($_GET['delete'])) {
				$db->q('DELETE FROM `orderformoptions` WHERE `parent` = ?', $_GET['delete']);
				$db->q('DELETE FROM `orderformitems` WHERE `id` = ?', $_GET['delete']);
				$billic->redirect('/Admin/OrderForms/Edit/' . urlencode($orderform['name']) . '/');
			}
			if (isset($_GET['deleteoption'])) {
				$db->q('DELETE FROM `orderformoptions` WHERE `id` = ?', $_GET['deleteoption']);
				$billic->redirect('/Admin/OrderForms/Edit/' . urlencode($orderform['name']) . '/');
			}
			if (isset($_POST['update'])) {
				if (is_array($_POST['name'])) {
					foreach ($_POST['name'] as $id => $name) {
						$db->q('UPDATE `orderformitems` SET `name` = ? WHERE `id` = ?', $name, $id);
					}
				}
				if (is_array($_POST['order'])) {
					foreach ($_POST['order'] as $id => $order) {
						$db->q('UPDATE `orderformitems` SET `price` = ?, `order` = ? WHERE `id` = ?', $_POST['price'][$id], $order, $id);
					}
				}
				if (is_array($_POST['order_option'])) {
					foreach ($_POST['order_option'] as $id => $order) {
						$db->q('UPDATE `orderformoptions` SET `price` = ?, `order` = ? WHERE `id` = ?', $_POST['price_option'][$id], $order, $id);
					}
				}
				if (is_array($_POST['order_option_name'])) {
					foreach ($_POST['order_option_name'] as $id => $order) {
						$db->q('UPDATE `orderformoptions` SET `name` = ? WHERE `id` = ?', $_POST['order_option_name'][$id], $id);
					}
				}
				if (is_array($_POST['min'])) {
					foreach ($_POST['min'] as $id => $min) {
						if ($_POST['step'][$id] < 1) {
							$_POST['step'][$id] = 1;
						}
						$db->q('UPDATE `orderformitems` SET `min` = ?, `max` = ?, `step` = ? WHERE `id` = ?', $min, $_POST['max'][$id], $_POST['step'][$id], $id);
					}
				}
				if (is_array($_POST['module_var'])) {
					foreach ($_POST['module_var'] as $id => $module_var) {
						$db->q('UPDATE `orderformitems` SET `module_var` = ? WHERE `id` = ?', $module_var, $id);
					}
				}
				if (is_array($_POST['opt_module_var'])) {
					foreach ($_POST['opt_module_var'] as $id => $opt_module_var) {
						$db->q('UPDATE `orderformoptions` SET `module_var` = ? WHERE `id` = ?', $opt_module_var, $id);
					}
				}
				if (is_array($_POST['requirement'])) {
					foreach ($_POST['requirement'] as $id => $requirement) {
						$db->q('UPDATE `orderformitems` SET `requirement` = ? WHERE `id` = ?', $requirement, $id);
					}
				}
				$billic->status = 'updated';
			}
			if (is_array($_POST['addoptionname'])) {
				foreach ($_POST['addoptionname'] as $parent => $name) {
					if (empty($name)) {
						continue;
					}
					$db->insert('orderformoptions', array(
						'name' => $name,
						'parent' => $parent,
					));
					$billic->status = 'added';
				}
			}
			if (isset($_POST['update_module'])) {
				if ($imported === true) {
					$_POST['module'] = 'RemoteBillicService';
					$_POST['orderform_name'] = $orderform_realname;
				} else {
					if (strlen($_POST['name']) > 127) {
						die('Name must be less than 128 characters');
					}
				}
				$db->q('UPDATE `orderforms` SET `module` = ?, `name` = ?, `title` = ? WHERE `id` = ?', $_POST['module'], $_POST['orderform_name'], $_POST['orderform_title'], $orderform['id']);
				$billic->redirect('/Admin/OrderForms/Edit/' . urlencode($_POST['orderform_name']) . '/');
			}
			if (empty($orderform['module'])) {
				$orderform_vars = array();
			} else {
				$billic->module($orderform['module']);
				$orderform_vars = $billic->modules[$orderform['module']]->settings['orderform_vars'];
			}
			$billic->show_errors();
			echo '<div class="row"><div class="col-md-6">';
			echo '<h3>Settings</h3>';
			echo '<form method="POST"><table class="table">';
			echo '<tr><td width="200">Provision Module</td><td>';
			if ($imported === true) {
				echo 'RemoteBillicService (Imported)';
			} else {
				echo '<select class="form-control" name="module"><option value="">- None -</option>';
				$modules = $billic->module_list_function('create');
				foreach ($modules as $module) {
					echo '<option value="' . $module['id'] . '"' . ($module['id'] == $orderform['module'] ? ' selected' : '') . '>' . $module['id'] . '</option>';
				}
				echo '</select>';
			}
			echo '</td></tr>';
			if ($imported === false) {
				echo '<tr><td>Order Form Name</td><td><input type="text" class="form-control" name="orderform_name" value="' . safe($orderform['name']) . '"></td></tr>';
			}
			//echo '<tr><td>Order Form Title</td><td><input type="text" class="form-control" name="orderform_title" value="'.safe($orderform['title']).'"></td></tr>';
			echo '<tr><td colspan="2" align="center"><input type="submit" class="btn btn-success" name="update_module" value="Update &raquo;"></td></tr></table></form>';
			echo '</div><div class="col-md-6">';
			echo '<form  method="POST" class="form-inline">
			<h3>Add new field</h3>
  <div class="form-group">
    <label class="sr-only" for="new_name">Name</label>
    <input type="text" class="form-control" name="label" id="new_name" placeholder="Name">
  </div>
  <div class="form-group">
    <label class="sr-only" for="new_type">Type</label>
	<select class="form-control" name="type" id="new_type">';
			foreach ($input_types as $type) {
				echo '<option value="' . $type . '">' . $type . '</option>';
			}
			echo '</select>	
  </div>
  <button type="submit" name="addfield" class="btn btn-success">Add Field &raquo;</button>
</form>';
			echo '</div></div>';
			echo '<form method="POST">';
			echo '<input type="submit" class="btn btn-success" name="update" value="Update &raquo;" style="position:absolute;left:-999px;top:-999px;height:0;width:0">'; // default button for "enter submits"
			echo '<table class="table table-hover">';
			$header = '<tr><th style="width:1px"></th><th style="min-width:200px">Name</th><th style="min-width:110px">Requirement</th><th style="min-width:200px">';
			if ($imported) {
				$header.= 'Original Name';
			} else {
				$header.= 'Module Variable';
			}
			$header.= '</th><th>Type</th><th style="min-width:80px">Unit Price</th><th style="width:100px">Order</th><th>Action</th></tr>';
			echo $header;
			$orderformitems = $db->q('SELECT * FROM `orderformitems` WHERE `parent` = ? ORDER BY `order`, `name` ASC', $orderform['id']);
			if (empty($orderformitems)) {
				echo '<tr><td colspan="20">No fields exist yet. Add one above.</td></tr>';
			}
			$order = 0;
			$image_forms = '';
			foreach ($orderformitems as $r) {
				if ($order > 0) {
					echo $header;
				}
				$order++;
				echo '<tr><td>' . (empty($r['img']) ? '' : '<img src="/i/orderformitems/' . $r['img'] . '" style="vertical-align:middle" class="pull-left">') . '</td><td><input type="text" class="form-control" name="name[' . $r['id'] . ']" value="' . safe($r['name']) . '"></td><td><select class="form-control" name="requirement[' . $r['id'] . ']" style="width: 110px">';
				foreach ($requirement_types as $type) {
					echo '<option value="' . $type . '"' . ($r['requirement'] == $type ? ' selected' : '') . '>' . (empty($type) ? 'None' : ucwords($type)) . '</option>';
				}
				echo '</select></td><td>';
				if ($imported === true) {
					echo safe($r['module_var']);
				} else {
					echo '<select class="form-control" name="module_var[' . $r['id'] . ']"><option value="">N/A</option>';
					foreach ($orderform_vars as $var) {
						echo '<option value="' . $var . '"' . ($r['module_var'] == $var ? ' selected' : '') . '>' . $var . '</option>';
					}
					echo '</select></td>';
				}
				echo '<td>' . $r['type'] . '</td><td>' . ($r['type'] == 'dropdown' || $r['type'] == 'dropdown_country' || $r['type'] == 'text' ? 'N/A' : '<input type="text" class="form-control" name="price[' . $r['id'] . ']" value="' . $r['price'] . '"  style="width: 100px">') . '</td><td><input type="text" class="form-control" name="order[' . $r['id'] . ']" size="2" value="' . $order . '"></td><td>';
				if (!$imported) {
					echo '<a href="delete/' . $r['id'] . '/" onclick="return confirm(\'Are you sure you want to delete?\');" class="btn btn-danger" title="Delete"><i class="icon-remove"></i></a>';
				}
				$image_forms.= '<form method="POST" enctype="multipart/form-data"><input type="file" id="img_' . $r['id'] . '" name="img_' . $r['id'] . '" onChange="this.form.submit();"></form>';
				echo '<a href="#" title="Upload Icon" onClick="$(\'#img_' . $r['id'] . '\').trigger(\'click\');" class="btn btn-default" title="Upload Icon"><i class="icon-paper-clip"></i></a>';
				echo '</td></tr>';
				if ($r['type'] == 'dropdown') {
					$options = $db->q('SELECT * FROM `orderformoptions` WHERE `parent` = ? ORDER BY `order`, `name` ASC', $r['id']);
					$order_opt = 0;
					foreach ($options as $option) {
						$order_opt++;
						echo '<tr><td></td><td><div class="input-group"><div class="input-group-addon"><i class="icon-arrow-right"></i></div><input type="text" class="form-control" name="order_option_name[' . $option['id'] . ']" value="' . safe($option['name']) . '"></div></td><td>&nbsp;</td><td>';
						if ($imported) {
							echo safe($option['module_var']);
						} else {
							echo '<input type="text" class="form-control" name="opt_module_var[' . $option['id'] . ']" value="' . $option['module_var'] . '">';
						}
						echo '</td><td>' . $option['type'] . '</td><td><input type="text" class="form-control" name="price_option[' . $option['id'] . ']" value="' . $option['price'] . '"></td><td><div class="input-group"><div class="input-group-addon"><i class="icon-arrow-right"></i></div><input type="text" class="form-control" name="order_option[' . $option['id'] . ']" value="' . $order_opt . '"></div></td><td>';
						if (!$imported) {
							echo '<a href="deleteoption/' . $option['id'] . '/" onclick="return confirm(\'Are you sure you want to delete?\');" class="btn btn-danger" title="Delete"><i class="icon-remove"></i></a>';
						}
						echo '</td></tr>';
					}
					if (!$imported) {
						echo '<tr><td></td><td colspan="10"><div class="input-group" style="width:100%"><div class="input-group-addon"><i class="icon-arrow-right"></i></div><input type="text" class="form-control" name="addoptionname[' . $r['id'] . ']" maxlength="50" style="width: 200px"><input type="submit" class="btn btn-success" name="addoption[' . $r['id'] . ']" value="Add Option &raquo;"></div></td></tr>';
					}
				}
				if ($r['type'] == 'slider') {
					echo '<tr><td></td><td colspan="10"><div class="input-group"><div class="input-group-addon"><i class="icon-arrow-right"></i> Min:</div><input type="text" class="form-control" name="min[' . $r['id'] . ']" maxlength="11" value="' . $r['min'] . '" style="width:75px"><div class="input-group-addon">Max:</div><input type="text" class="form-control" name="max[' . $r['id'] . ']" maxlength="11" value="' . $r['max'] . '" style="width:75px"><div class="input-group-addon">Step:</div><input type="text" class="form-control" name="step[' . $r['id'] . ']" maxlength="11" value="' . $r['step'] . '" style="width:75px"></div></td></tr>';
				}
			}
			echo '</table><div align="center"><input type="submit" class="btn btn-success" name="update" value="Update All &raquo;"></div></form>';
			echo '<div style="visibility:hidden;position:absolute">' . $image_forms . '</div>';
			return;
		}
		if (isset($_GET['New'])) {
			$title = 'New Order Form';
			$billic->set_title($title);
			echo '<h1>' . $title . '</h1>';
			if (array_key_exists('Lo', $billic->lic)) {
				$lic_count = $db->q('SELECT COUNT(*) FROM `orderforms`');
				$lic_count = $lic_count[0]['COUNT(*)'];
				if ($lic_count >= $billic->lic['Lo']) {
					err('Unable to create a new order form because you have reached your limit. Please upgrade your Billic License.');
				}
			}
			$billic->module('FormBuilder');
			$form = array(
				'name' => array(
					'label' => 'Name',
					'type' => 'text',
					'required' => true,
					'default' => '',
				) ,
			);
			if (isset($_POST['Continue'])) {
				$billic->modules['FormBuilder']->check_everything(array(
					'form' => $form,
				));
				if (strlen($_POST['name']) > 127) {
					$billic->error('Name must be less than 128 characters');
				}
				if (empty($billic->errors)) {
					$db->insert('orderforms', array(
						'name' => $_POST['name'],
					));
					$billic->redirect('/Admin/OrderForms/Edit/' . urlencode($_POST['name']) . '/');
				}
			}
			$billic->show_errors();
			$billic->modules['FormBuilder']->output(array(
				'form' => $form,
				'button' => 'Continue',
			));
			return;
		}
		if (isset($_GET['Delete'])) {
			$this->delete(urldecode($_GET['Delete']));
			$billic->status = 'deleted';
		}
		$title = 'Order Forms';
		$billic->set_title($title);
		echo '<h1><i class="icon-list"></i> ' . $title . '</h1>';
		if (array_key_exists('Lo', $billic->lic)) {
			$lic_count = $db->q('SELECT COUNT(*) FROM `orderforms`');
			$lic_count = $lic_count[0]['COUNT(*)'];
			$lic_percent = ceil((100 / $billic->lic['Lo']) * $lic_count);
			echo '<div class="alert alert-';
			if ($lic_percent >= 80) {
				echo 'danger';
			} else if ($lic_percent >= 60) {
				echo 'warning';
			} else {
				echo 'info';
			}
			echo '" role="alert">Your Billic license limits you to ' . $billic->lic['Lo'] . ' order forms. You are currently using ' . $lic_count . ' at ' . $lic_percent . '% capacity.</div>';
		}
		$billic->show_errors();
		echo '<a href="New/" class="btn btn-success"><i class="icon-plus"></i> New Order Form</a>';
		echo '<table class="table table-striped"><tr><th>Name</th><th style="width:20%">Actions</th></tr>';
		$orderforms = $db->q('SELECT * FROM `orderforms` ORDER BY `name` ASC');
		$imported_count = 0;
		foreach ($orderforms as $r) {
			if (strlen($r['name']) == 128) {
				$imported_count++;
				continue; // do not show imported order forms
				
			}
			echo '<tr><td><a href="Edit/' . urlencode($r['name']) . '/">' . safe($r['name']) . '</a></td><td>';
			echo '<a href="/Admin/OrderForms/Edit/' . urlencode($r['name']) . '/" class="btn btn-primary btn-xs"><i class="icon-edit-write"></i> Edit</a>';
			echo '&nbsp;<a href="/Admin/OrderForms/Delete/' . urlencode($r['name']) . '/" class="btn btn-danger btn-xs" title="Delete" onClick="return confirm(\'Are you sure you want to delete?\');"><i class="icon-remove"></i> Delete</a>';
			echo '</tr>';
		}
		if (count($orderforms) - $imported_count == 0) {
			echo '<tr><td colspan="10">You have no order forms.</td></tr>';
		}
		echo '</table>';
		if ($imported_count > 0) {
			echo 'There are ' . $imported_count . ' Order Forms of imported plans which are not shown. To edit them, <a href="/Admin/Plans">go to Plans</a>.';
		}
	}
	function delete($name) {
		global $billic, $db;
		$orderform = $db->q('SELECT * FROM `orderforms` WHERE `name` = ?', $name);
		$orderform = $orderform[0];
		if (empty($orderform)) {
			return false;
		}
		$items = $db->q('SELECT * FROM `orderformitems` WHERE `parent` = ?', $orderform['id']);
		foreach ($items as $item) {
			$options = $db->q('SELECT * FROM `orderformoptions` WHERE `parent` = ?', $item['id']);
			foreach ($options as $option) {
				$db->q('DELETE FROM `orderformoptions` WHERE `id` = ?', $option['id']);
			}
			$db->q('DELETE FROM `orderformitems` WHERE `id` = ?', $item['id']);
		}
		$db->q('DELETE FROM `billingcycles` WHERE `import_hash` = ?', $name);
		$db->q('DELETE FROM `orderforms` WHERE `id` = ?', $orderform['id']);
		return true;
	}
}
