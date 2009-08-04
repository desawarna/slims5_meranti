<?php
/**
 * Copyright (C) 2007,2008  Arie Nugraha (dicarve@yahoo.com)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 */

/* Item Status Management section */

// main system configuration
require '../../../sysconfig.inc.php';
// start the session
require SENAYAN_BASE_DIR.'admin/default/session.inc.php';
require SENAYAN_BASE_DIR.'admin/default/session_check.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/form_maker/simbio_form_table_AJAX.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/paging/simbio_paging_ajax.inc.php';
require SIMBIO_BASE_DIR.'simbio_DB/datagrid/simbio_dbgrid.inc.php';
require SIMBIO_BASE_DIR.'simbio_DB/simbio_dbop.inc.php';

// privileges checking
$can_read = utility::havePrivilege('master_file', 'r');
$can_write = utility::havePrivilege('master_file', 'w');

if (!$can_read) {
    die('<div class="errorBox">'.lang_sys_common_no_privilage.'</div>');
}

/* RECORD OPERATION */
if (isset($_POST['saveData'])) {
    $itemStatusID = strip_tags(trim($_POST['itemStatusID']));
    $itemStatusName = strip_tags(trim($_POST['itemStatus']));
    // check form validity
    if (empty($itemStatusID) OR empty($itemStatusName)) {
        utility::jsAlert(lang_mod_masterfile_itemstatus_alert_name_noempty);
        exit();
    } else {
        $data['item_status_id'] = $dbs->escape_string($itemStatusID);
        $data['item_status_name'] = $dbs->escape_string($itemStatusName);
        // parsing rules
        $rules = '';
        if (isset($_POST['rules']) AND !empty($_POST['rules'])) {
            $rules = serialize($_POST['rules']);
        } else {
            $rules = 'literal{NULL}';
        }
        $data['rules'] = $rules;
        $data['input_date'] = date('Y-m-d');
        $data['last_update'] = date('Y-m-d');

        // create sql op object
        $sql_op = new simbio_dbop($dbs);
        if (isset($_POST['updateRecordID'])) {
            /* UPDATE RECORD MODE */
            // remove input date
            unset($data['input_date']);
            // filter update record ID
            $updateRecordID = $dbs->escape_string(trim($_POST['updateRecordID']));
            // update the data
            $update = $sql_op->update('mst_item_status', $data, 'item_status_id=\''.$updateRecordID.'\'');
            if ($update) {
                utility::jsAlert(lang_mod_masterfile_itemstatus_alert_update_ok);
                // update item status ID in item table to keep data integrity
                $sql_op->update('item', array('item_status_id' => $data['item_status_id']), 'item_status_id=\''.$updateRecordID.'\'');
                echo '<script type="text/javascript">parent.setContent(\'mainContent\', parent.getPreviousAJAXurl(), \'post\');</script>';
            } else { utility::jsAlert(lang_mod_masterfile_itemstatus_alert_update_fail."\nDEBUG : ".$sql_op->error); }
            exit();
        } else {
            /* INSERT RECORD MODE */
            // insert the data
            $insert = $sql_op->insert('mst_item_status', $data);
            if ($insert) {
                utility::jsAlert(lang_mod_masterfile_itemstatus_alert_new_add_ok);
                echo '<script type="text/javascript">parent.setContent(\'mainContent\', \''.$_SERVER['PHP_SELF'].'\', \'post\');</script>';
            } else { utility::jsAlert(lang_mod_masterfile_itemstatus_alert_add_fail."\n".$sql_op->error); }
            exit();
        }
    }
    exit();
} else if (isset($_POST['itemID']) AND !empty($_POST['itemID']) AND isset($_POST['itemAction'])) {
    if (!($can_read AND $can_write)) {
        die();
    }
    /* DATA DELETION PROCESS */
    $sql_op = new simbio_dbop($dbs);
    $failed_array = array();
    $error_num = 0;
    if (!is_array($_POST['itemID'])) {
        // make an array
        $_POST['itemID'] = array($dbs->escape_string(trim($_POST['itemID'])));
    }
    // loop array
    foreach ($_POST['itemID'] as $itemID) {
        $itemID = $dbs->escape_string(trim($itemID));
        if (!$sql_op->delete('mst_item_status', "item_status_id='$itemID'")) {
            $error_num++;
        }
    }

    // error alerting
    if ($error_num == 0) {
        utility::jsAlert('lang_mod_masterfile_itemstatus_alert_all_delete_ok');
        echo '<script type="text/javascript">parent.setContent(\'mainContent\', \''.$_SERVER['PHP_SELF'].'?'.$_POST['lastQueryStr'].'\', \'post\');</script>';
    } else {
        utility::jsAlert(lang_mod_masterfile_itemstatus_alert_all_delete_fail);
        echo '<script type="text/javascript">parent.setContent(\'mainContent\', \''.$_SERVER['PHP_SELF'].'?'.$_POST['lastQueryStr'].'\', \'post\');</script>';
    }
    exit();
}
/* item status update process end */

// item status rules
$rules_option[] = array(NO_LOAN_TRANSACTION, 'No Loan Transaction');
$rules_option[] = array(STOCK_TAKE_SKIP, 'Skipped By Stock Take');

/* search form */
?>
<fieldset class="menuBox">
<div class="menuBoxInner masterFileIcon">
    <?php echo strtoupper(lang_mod_masterfile_itemstatus); ?> - <a href="#" onclick="setContent('mainContent', '<?php echo MODULES_WEB_ROOT_DIR; ?>master_file/item_status.php?action=detail', 'get');" class="headerText2"><?php echo lang_mod_masterfile_itemstatus_new_add; ?></a>
    &nbsp; <a href="#" onclick="setContent('mainContent', '<?php echo MODULES_WEB_ROOT_DIR; ?>master_file/item_status.php', 'get');" class="headerText2"><?php echo lang_mod_masterfile_itemstatus; ?></a>
    <hr />
    <form name="search" action="blank.html" target="blindSubmit" onsubmit="$('doSearch').click();" id="search" method="get" style="display: inline;"><?php echo lang_sys_common_form_search_field; ?> :
    <input type="text" name="keywords" size="30" />
    <input type="button" id="doSearch" onclick="setContent('mainContent', '<?php echo MODULES_WEB_ROOT_DIR; ?>master_file/item_status.php?' + $('search').serialize(), 'post')" value="<?php echo lang_sys_common_form_search; ?>" class="button" />
    </form>
</div>
</fieldset>
<?php
/* search form end */
/* main content */
if (isset($_POST['detail']) OR (isset($_GET['action']) AND $_GET['action'] == 'detail')) {
    if (!($can_read AND $can_write)) {
        die('<div class="errorBox">'.lang_sys_common_no_privilage.'</div>');
    }
    /* RECORD FORM */
    $itemID = trim($dbs->escape_string(isset($_POST['itemID'])?$_POST['itemID']:''));
    $rec_q = $dbs->query("SELECT * FROM mst_item_status WHERE item_status_id='$itemID'");
    $rec_d = $rec_q->fetch_assoc();

    // create new instance
    $form = new simbio_form_table_AJAX('mainForm', $_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING'], 'post');
    $form->submit_button_attr = 'name="saveData" value="'.lang_sys_common_form_save_change.'" class="button"';

    // form table attributes
    $form->table_attr = 'align="center" id="dataList" cellpadding="5" cellspacing="0"';
    $form->table_header_attr = 'class="alterCell" style="font-weight: bold;"';
    $form->table_content_attr = 'class="alterCell2"';

    // edit mode flag set
    if ($rec_q->num_rows > 0) {
        $form->edit_mode = true;
        // record ID for delete process
        $form->record_id = $itemID;
        // form record title
        $form->record_title = $rec_d['item_status_name'];
        // submit button attribute
        $form->submit_button_attr = 'name="saveData" value="'.lang_sys_common_form_update.'" class="button"';
    }

    /* Form Element(s) */
    // item status code
    $form->addTextField('text', 'itemStatusID', lang_mod_masterfile_itemstatus_form_field_code.'*', $rec_d['item_status_id'], 'style="width: 20%;" maxlength="3"');
    // item status name
    $form->addTextField('text', 'itemStatus', lang_mod_masterfile_itemstatus_form_field_name.'*', $rec_d['item_status_name'], 'style="width: 60%;"');
    // item status rules
    $form->addCheckbox('rules', lang_mod_masterfile_itemstatus_form_field_rules, $rules_option, unserialize($rec_d['rules']));

    // edit mode messagge
    if ($form->edit_mode) {
        echo '<div class="infoBox">'.lang_mod_masterfile_itemstatus_common_edit_info.' : <b>'.$rec_d['item_status_name'].'</b>  <br />'.lang_mod_masterfile_itemstatus_common_last_update.$rec_d['last_update'].'</div>';
    }
    // print out the form object
    echo $form->printOut();
} else {
    /* ITEM STATUS LIST */
    // table spec
    $table_spec = 'mst_item_status AS ist';

    // create datagrid
    $datagrid = new simbio_datagrid();
    if ($can_read AND $can_write) {
        $datagrid->setSQLColumn('ist.item_status_id',
            'ist.item_status_id AS \''.lang_mod_masterfile_itemstatus_form_field_code.'\'',
            'ist.item_status_name AS \''.lang_mod_masterfile_itemstatus_form_field_name.'\'',
            'ist.last_update AS \''.lang_mod_masterfile_itemstatus_common_last_update.'\'');
    } else {
        $datagrid->setSQLColumn('ist.item_status_id AS \''.lang_mod_masterfile_itemstatus_form_field_code.'\'',
            'ist.item_status_name AS \''.lang_mod_masterfile_itemstatus_form_field_name.'\'',
            'ist.last_update AS \''.lang_mod_masterfile_itemstatus_common_last_update.'\'');
    }
    $datagrid->setSQLorder('item_status_name ASC');

    // change the record order
    if (isset($_GET['fld']) AND isset($_GET['dir'])) {
        $datagrid->setSQLorder("'".urldecode($_GET['fld'])."' ".$dbs->escape_string($_GET['dir']));
    }

    // is there any search
    if (isset($_GET['keywords']) AND $_GET['keywords']) {
       $keywords = $dbs->escape_string($_GET['keywords']);
       $datagrid->setSQLCriteria("ist.item_status_name LIKE '%$keywords%'");
    }

    // set table and table header attributes
    $datagrid->table_attr = 'align="center" id="dataList" cellpadding="5" cellspacing="0"';
    $datagrid->table_header_attr = 'class="dataListHeader" style="font-weight: bold;"';
    // set delete proccess URL
    $datagrid->chbox_form_URL = $_SERVER['PHP_SELF'];

    // put the result into variables
    $datagrid_result = $datagrid->createDataGrid($dbs, $table_spec, 20, ($can_read AND $can_write));
    if (isset($_GET['keywords']) AND $_GET['keywords']) {
        $msg = str_replace('{result->num_rows}', $datagrid->num_rows, lang_sys_common_search_result_info);
        echo '<div class="infoBox">'.$msg.' : "'.$_GET['keywords'].'"</div>';
    }

    echo $datagrid_result;
}
/* main content end */
?>
