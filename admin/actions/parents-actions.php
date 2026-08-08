<?php
/**
 * Parent Actions — add, edit, delete, with bidirectional student linking
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

$upload_dir = __DIR__ . '/../uploads/photos/parents/';

function sync_parent_student_links(PDO $pdo, string $school_uuid, string $parent_uuid, array $new_student_uuids): void {
    // 1. Update parents.linked_student_uuids
    $link_str = implode(',', array_filter($new_student_uuids));
    $pdo->prepare("UPDATE parents SET linked_student_uuids=? WHERE parent_uuid=? AND school_uuid=?")
        ->execute([$link_str, $parent_uuid, $school_uuid]);

    // 2. Set parent_uuid on each linked student
    if ($new_student_uuids) {
        $in = implode(',', array_fill(0, count($new_student_uuids), '?'));
        $pdo->prepare("UPDATE students SET parent_uuid=? WHERE student_uuid IN ($in) AND school_uuid=?")
            ->execute(array_merge([$parent_uuid], $new_student_uuids, [$school_uuid]));
    }

    // 3. Clear parent_uuid on previously linked students no longer in the list
    $pdo->prepare("UPDATE students SET parent_uuid=NULL
        WHERE parent_uuid=? AND school_uuid=?"
        . ($new_student_uuids ? " AND student_uuid NOT IN (" . implode(',', array_fill(0, count($new_student_uuids), '?')) . ")" : ""))
        ->execute(array_merge([$parent_uuid, $school_uuid], $new_student_uuids));
}

// ── ADD PARENT ────────────────────────────────────────────────────────────────
if (isset($_POST['action_add_parent'])) {
    if (!in_array($active_role, ['School Admin','Platform Manager']) && !can_manage($active_role, feature_access('parents'))) {
        $error_msg = 'You do not have permission to add parents.';
        return;
    }

    $name    = safe_str($_POST['parent_name']  ?? '');
    $email   = safe_str($_POST['parent_email'] ?? '');
    $phone   = safe_str($_POST['parent_phone'] ?? '');
    $address = safe_str($_POST['address']      ?? '');
    $occ     = safe_str($_POST['occupation']   ?? '');
    $emp     = safe_str($_POST['employer']     ?? '');

    // ✅ Duplicate name check
    $checkName = $pdo->prepare("SELECT parent_uuid FROM parents WHERE school_uuid = ? AND LOWER(name) = LOWER(?) LIMIT 1");
    $checkName->execute([$school_uuid, $name]);
    if ($checkName->fetchColumn()) {
        $error_msg = "A parent with the name '$name' already exists.";
        return;
    }

    // ✅ Duplicate email check
    if (!empty($email)) {
        $checkEmail = $pdo->prepare("SELECT parent_uuid FROM parents WHERE school_uuid = ? AND LOWER(email) = LOWER(?) LIMIT 1");
        $checkEmail->execute([$school_uuid, $email]);
        if ($checkEmail->fetchColumn()) {
            $error_msg = "A parent with the email '$email' already exists.";
            return;
        }
    }

    $photo_error = null;
    $photo   = handle_image_upload('parent_photo', $upload_dir, 'prt_', '', 5_242_880, $photo_error);

    // Multi-select student UUIDs
    $raw_uuids = $_POST['linked_student_uuids'] ?? [];
    $linked    = array_filter(array_map('trim', (array)$raw_uuids));

    if (empty($name) || empty($phone) || empty($email)) {
        $error_msg = 'Name, phone and email are required.';
        return;
    }

    try {
        $uuid = uid('prt');
        $pdo->prepare("INSERT INTO parents (parent_uuid,school_uuid,name,email,phone,address,occupation,photo_path,linked_student_uuids,status)
            VALUES (?,?,?,?,?,?,?,?,?,'Active')")
            ->execute([$uuid,$school_uuid,$name,$email,$phone,$address,$occ,$photo, implode(',', $linked)]);

        // Update employer if field exists (graceful — silently skip if column missing)
        try { $pdo->prepare("UPDATE parents SET employer=? WHERE parent_uuid=?")->execute([$emp, $uuid]); } catch(Exception $e){}

        sync_parent_student_links($pdo, $school_uuid, $uuid, $linked);
        AuditLog::write($pdo,$school_uuid,$user_uuid,'parent.create',$uuid,"Added $name");
        $success_msg = "Parent $name added and children linked!";
        if (!empty($photo_error)) {
            $success_msg .= ' ⚠ ' . $photo_error;
        }
    } catch (PDOException $e) {
        $error_msg = safe_error('Failed', $e);
    }
}

// ── EDIT PARENT ───────────────────────────────────────────────────────────────
if (isset($_POST['action_edit_parent'])) {
    if (!in_array($active_role, ['School Admin','Platform Manager']) && !can_manage($active_role, feature_access('parents'))) {
        $error_msg = 'You do not have permission to edit parents.';
        return;
    }

    $uuid    = safe_str($_POST['parent_uuid']  ?? '');
    $name    = safe_str($_POST['parent_name']  ?? '');
    $email   = safe_str($_POST['parent_email'] ?? '');
    $phone   = safe_str($_POST['parent_phone'] ?? '');
    $address = safe_str($_POST['address']      ?? '');
    $occ     = safe_str($_POST['occupation']   ?? '');
    $emp     = safe_str($_POST['employer']     ?? '');

    // ✅ Duplicate name check (exclude current parent)
    $checkName = $pdo->prepare("SELECT parent_uuid FROM parents WHERE school_uuid = ? AND LOWER(name) = LOWER(?) AND parent_uuid != ? LIMIT 1");
    $checkName->execute([$school_uuid, $name, $uuid]);
    if ($checkName->fetchColumn()) {
        $error_msg = "A parent with the name '$name' already exists.";
        return;
    }

    // ✅ Duplicate email check (exclude current parent)
    if (!empty($email)) {
        $checkEmail = $pdo->prepare("SELECT parent_uuid FROM parents WHERE school_uuid = ? AND LOWER(email) = LOWER(?) AND parent_uuid != ? LIMIT 1");
        $checkEmail->execute([$school_uuid, $email, $uuid]);
        if ($checkEmail->fetchColumn()) {
            $error_msg = "A parent with the email '$email' already exists.";
            return;
        }
    }

    $photo_error = null;
    $photo   = handle_image_upload('parent_photo', $upload_dir, 'prt_', safe_str($_POST['existing_photo'] ?? ''), 5_242_880, $photo_error);

    $raw_uuids = $_POST['linked_student_uuids'] ?? [];
    $linked    = array_filter(array_map('trim', (array)$raw_uuids));

    if (empty($name) || empty($phone)) {
        $error_msg = 'Name and phone are required.';
        return;
    }

    try {
        $pdo->prepare("UPDATE parents SET name=?,email=?,phone=?,address=?,occupation=?,photo_path=?,linked_student_uuids=? WHERE parent_uuid=? AND school_uuid=?")
            ->execute([$name,$email,$phone,$address,$occ,$photo, implode(',', $linked), $uuid,$school_uuid]);
        try { $pdo->prepare("UPDATE parents SET employer=? WHERE parent_uuid=?")->execute([$emp, $uuid]); } catch(Exception $e){}

        sync_parent_student_links($pdo, $school_uuid, $uuid, $linked);
        AuditLog::write($pdo,$school_uuid,$user_uuid,'parent.update',$uuid,"Updated $name");
        $success_msg = 'Parent updated and children re-linked!';
        if (!empty($photo_error)) {
            $success_msg .= ' ⚠ ' . $photo_error;
        }
    } catch (PDOException $e) {
        $error_msg = safe_error('Update failed', $e);
    }
}

// ── DELETE PARENT ─────────────────────────────────────────────────────────────
if (isset($_POST['action_delete_parent'])) {
    if ($active_role !== 'School Admin') {
        $error_msg = 'Only School Admin can delete.';
        return;
    }
    $uuid = safe_str($_POST['parent_uuid'] ?? '');
    try {
        // Clear parent_uuid from children first
        $pdo->prepare("UPDATE students SET parent_uuid=NULL WHERE parent_uuid=? AND school_uuid=?")->execute([$uuid,$school_uuid]);
        $pdo->prepare("DELETE FROM parents WHERE parent_uuid=? AND school_uuid=?")->execute([$uuid,$school_uuid]);
        AuditLog::write($pdo,$school_uuid,$user_uuid,'parent.delete',$uuid,'Deleted');
        $success_msg = 'Parent deleted.';
    } catch (Exception $e) {
        $error_msg = 'Deletion failed.';
    }
}