<?php
require('admin.inc.php');
require_once(INCLUDE_DIR."/class.dynamic_forms.php");

$form=null;
if($_REQUEST['id'] && !($form=DynamicForm::lookup($_REQUEST['id'])))
    $errors['err']='Unknown or invalid dynamic form ID.';

if($_POST) {
    $fields = array('title', 'notes', 'instructions');
    $required = array('subject');
    $max_sort = 0;
    switch(strtolower($_POST['do'])) {
        case 'update':
            foreach ($fields as $f)
                if (isset($_POST[$f]))
                    $form->set($f, $_POST[$f]);
            if ($form->isValid())
                $form->save(true);
            foreach ($form->getDynamicFields() as $field) {
                $id = $field->get('id');
                if ($_POST["delete-$id"] == 'on' && $field->isDeletable()) {
                    $field->delete();
                    // Don't bother updating the field
                    continue;
                }
                if (isset($_POST["type-$id"]) && $field->isChangeable())
                    $field->set('type', $_POST["type-$id"]);
                if (isset($_POST["name-$id"]) && !$field->isNameForced())
                    $field->set('name', $_POST["name-$id"]);
                # TODO: make sure all help topics still have all required fields
                if (!$field->isRequirementForced())
                    $field->set('required', $_POST["required-$id"] == 'on' ?  1 : 0);
                if (!$field->isPrivacyForced())
                    $field->set('private', $_POST["private-$id"] == 'on' ?  1 : 0);
                foreach (array('sort','label') as $f) {
                    if (isset($_POST["$f-$id"])) {
                        $field->set($f, $_POST["$f-$id"]);
                    }
                }
                if ($field->isValid())
                    $field->save();
                // Keep track of the last sort number
                $max_sort = max($max_sort, $field->get('sort'));
            }
            break;
        case 'add':
            $form = DynamicForm::create(array(
                'title'=>$_POST['title'],
                'instructions'=>$_POST['instructions'],
                'notes'=>$_POST['notes']));
            if ($form->isValid())
                $form->save(true);
            break;

        case 'mass_process':
            if(!$_POST['ids'] || !is_array($_POST['ids']) || !count($_POST['ids'])) {
                $errors['err'] = 'You must select at least one API key';
            } else {
                $count = count($_POST['ids']);
                switch(strtolower($_POST['a'])) {
                    case 'delete':
                        $i=0;
                        foreach($_POST['ids'] as $k=>$v) {
                            if(($t=DynamicForm::lookup($v)) && $t->delete())
                                $i++;
                        }
                        if ($i && $i==$count)
                            $msg = 'Selected custom forms deleted successfully';
                        elseif ($i > 0)
                            $warn = "$i of $count selected forms deleted";
                        elseif (!$errors['err'])
                            $errors['err'] = 'Unable to delete selected custom forms';
                        break;
                }
            }
            break;
    }

    if ($form) {
        for ($i=0; isset($_POST["sort-new-$i"]); $i++) {
            if (!$_POST["label-new-$i"])
                continue;
            $field = DynamicFormField::create(array(
                'form_id'=>$form->get('id'),
                'sort'=>$_POST["sort-new-$i"] ? $_POST["sort-new-$i"] : $max_sort++,
                'label'=>$_POST["label-new-$i"],
                'type'=>$_POST["type-new-$i"],
                'name'=>$_POST["name-new-$i"],
                'private'=>$_POST["private-new-$i"] == 'on' ? 1 : 0,
                'required'=>$_POST["required-new-$i"] == 'on' ? 1 : 0
            ));
            if ($field->isValid())
                $field->save();
        }
        // XXX: Move to an instrumented list that can handle this better
        $form->_dfields = $form->_fields = null;
    }
}

$page='dynamic-forms.inc.php';
if($form || ($_REQUEST['a'] && !strcasecmp($_REQUEST['a'],'add')))
    $page='dynamic-form.inc.php';

$nav->setTabActive('manage');
require(STAFFINC_DIR.'header.inc.php');
require(STAFFINC_DIR.$page);
include(STAFFINC_DIR.'footer.inc.php');
?>
