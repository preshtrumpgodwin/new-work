<?php
/**
 * Cafeteria & Meals Actions — menu items, meal plans, billing.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

// ── MENU ITEMS ───────────────────────────────────────────────────────────────
if (isset($_POST['action_add_menu_item'])) {
    if (!(in_array($active_role, ['School Admin','Platform Manager']) || can_manage($active_role, feature_access('cafeteria_meals')))) {
        $error_msg = 'You do not have permission to manage the cafeteria menu.'; return;
    }
    $name = safe_str($_POST['item_name'] ?? '');
    $type = safe_str($_POST['meal_type'] ?? 'Lunch');
    $price = max(0, (float)($_POST['price'] ?? 0));
    if ($name) {
        try {
            $uuid = uid('menu');
            $pdo->prepare("INSERT INTO cafeteria_menu_items (item_uuid,school_uuid,item_name,meal_type,price,is_active) VALUES (?,?,?,?,?,1)")
                ->execute([$uuid, $school_uuid, $name, $type, $price]);
            $success_msg = "Menu item '$name' added!";
        } catch (Exception $e) { $error_msg = 'Menu tables not found — run the Phase 9 migration first.'; }
    } else { $error_msg = 'Item name is required.'; }
}
if (isset($_POST['action_toggle_menu_item']) && (in_array($active_role, ['School Admin','Platform Manager']) || can_manage($active_role, feature_access('cafeteria_meals')))) {
    $uuid = safe_str($_POST['item_uuid'] ?? '');
    $pdo->prepare("UPDATE cafeteria_menu_items SET is_active = 1 - is_active WHERE item_uuid=? AND school_uuid=?")->execute([$uuid, $school_uuid]);
    $success_msg = 'Menu item updated.';
}
if (isset($_POST['action_delete_menu_item']) && (in_array($active_role, ['School Admin','Platform Manager']) || can_manage($active_role, feature_access('cafeteria_meals')))) {
    $uuid = safe_str($_POST['item_uuid'] ?? '');
    $pdo->prepare("DELETE FROM cafeteria_menu_items WHERE item_uuid=? AND school_uuid=?")->execute([$uuid, $school_uuid]);
    $success_msg = 'Menu item removed.';
}

// ── MEAL PLANS ───────────────────────────────────────────────────────────────
if (isset($_POST['action_add_meal_plan'])) {
    if (!(in_array($active_role, ['School Admin','Platform Manager']) || can_manage($active_role, feature_access('cafeteria_meals')))) {
        $error_msg = 'You do not have permission to manage meal plans.'; return;
    }
    $student_uuid = safe_str($_POST['student_uuid'] ?? '');
    $plan_type = safe_str($_POST['plan_type'] ?? 'Daily');
    $amount = max(0, (float)($_POST['amount'] ?? 0));
    $start = safe_str($_POST['start_date'] ?? date('Y-m-d'));
    $end = safe_str($_POST['end_date'] ?? '');

    $sn = $pdo->prepare("SELECT name FROM students WHERE student_uuid=? AND school_uuid=?");
    $sn->execute([$student_uuid, $school_uuid]);
    $student_name = $sn->fetchColumn();

    if ($student_name && $amount > 0) {
        try {
            $uuid = uid('mp');
            $pdo->prepare("INSERT INTO cafeteria_meal_plans (plan_uuid,school_uuid,student_uuid,student_name,plan_type,amount,start_date,end_date,status) VALUES (?,?,?,?,?,?,?,?,'Active')")
                ->execute([$uuid, $school_uuid, $student_uuid, $student_name, $plan_type, $amount, $start, $end ?: null]);
            AuditLog::write($pdo, $school_uuid, $user_uuid, 'cafeteria.plan_create', $uuid, "Meal plan for $student_name");
            $success_msg = "Meal plan created for $student_name!";
        } catch (Exception $e) { $error_msg = 'Meal plan tables not found — run the Phase 9 migration first.'; }
    } else {
        $error_msg = 'Select a student and enter a valid amount.';
    }
}
if (isset($_POST['action_cancel_meal_plan']) && (in_array($active_role, ['School Admin','Platform Manager']) || can_manage($active_role, feature_access('cafeteria_meals')))) {
    $uuid = safe_str($_POST['plan_uuid'] ?? '');
    $pdo->prepare("UPDATE cafeteria_meal_plans SET status='Cancelled' WHERE plan_uuid=? AND school_uuid=?")->execute([$uuid, $school_uuid]);
    $success_msg = 'Meal plan cancelled.';
}

// ── BILLING ──────────────────────────────────────────────────────────────────
if (isset($_POST['action_add_cafeteria_bill'])) {
    if (!(in_array($active_role, ['School Admin','Platform Manager']) || can_manage($active_role, feature_access('cafeteria_meals')))) {
        $error_msg = 'You do not have permission to bill the cafeteria.'; return;
    }
    $student_uuid = safe_str($_POST['student_uuid'] ?? '');
    $plan_uuid = safe_str($_POST['plan_uuid'] ?? '') ?: null;
    $amount = max(0, (float)($_POST['amount'] ?? 0));
    $bdate = safe_str($_POST['billing_date'] ?? date('Y-m-d'));
    $notes = safe_str($_POST['notes'] ?? '');

    $sn = $pdo->prepare("SELECT name FROM students WHERE student_uuid=? AND school_uuid=?");
    $sn->execute([$student_uuid, $school_uuid]);
    $student_name = $sn->fetchColumn();

    if ($student_name && $amount > 0) {
        $uuid = uid('cbill');
        $pdo->prepare("INSERT INTO cafeteria_billing (bill_uuid,school_uuid,student_uuid,student_name,plan_uuid,amount,billing_date,status,notes) VALUES (?,?,?,?,?,?,?,'Unpaid',?)")
            ->execute([$uuid, $school_uuid, $student_uuid, $student_name, $plan_uuid, $amount, $bdate, $notes]);
        AuditLog::write($pdo, $school_uuid, $user_uuid, 'cafeteria.bill_create', $uuid, "Billed $student_name ₦$amount");
        $success_msg = "Billed $student_name!";
    } else {
        $error_msg = 'Select a student and enter a valid amount.';
    }
}
if (isset($_POST['action_mark_cafeteria_paid']) && (in_array($active_role, ['School Admin','Platform Manager']) || can_manage($active_role, feature_access('cafeteria_meals')))) {
    $uuid = safe_str($_POST['bill_uuid'] ?? '');
    $pdo->prepare("UPDATE cafeteria_billing SET status='Paid' WHERE bill_uuid=? AND school_uuid=?")->execute([$uuid, $school_uuid]);
    $success_msg = 'Marked as paid.';
}
