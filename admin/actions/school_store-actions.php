<?php
/**
 * Actions: Inventory / School Store + POS (admin/sections/school_store.php)
 * Split out of the old phase3-actions.php grouping.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

if (isset($_POST['action_add_store_item']) && in_array($active_role, ['School Admin','Platform Manager'], true)) {
    $name  = safe_str($_POST['item_name']  ?? '');
    $cat   = safe_str($_POST['category']   ?? 'Uniform');
    $price = max(0, (float)($_POST['unit_price'] ?? 0));
    $qty   = max(0, safe_int($_POST['stock_quantity'] ?? 0));
    $reorder = max(0, safe_int($_POST['reorder_level'] ?? 10));
    if ($name) {
        $uuid = uid('itm');
        $pdo->prepare("INSERT INTO store_inventory (item_uuid,school_uuid,item_name,category,unit_price,stock_quantity,reorder_level) VALUES (?,?,?,?,?,?,?)")
            ->execute([$uuid,$school_uuid,$name,$cat,$price,$qty,$reorder]);
        $success_msg = "Item '$name' added to inventory!";
    } else { $error_msg = 'Item name is required.'; }
}
if (isset($_POST['action_edit_store_item']) && in_array($active_role, ['School Admin','Platform Manager'], true)) {
    $iu = safe_str($_POST['item_uuid'] ?? '');
    $pdo->prepare("UPDATE store_inventory SET item_name=?, category=?, unit_price=?, stock_quantity=?, reorder_level=? WHERE item_uuid=? AND school_uuid=?")
        ->execute([safe_str($_POST['item_name']??''), safe_str($_POST['category']??'Uniform'), max(0,(float)($_POST['unit_price']??0)), max(0,safe_int($_POST['stock_quantity']??0)), max(0,safe_int($_POST['reorder_level']??10)), $iu, $school_uuid]);
    $success_msg = 'Item updated!';
}
if (isset($_POST['action_delete_store_item']) && in_array($active_role, ['School Admin','Platform Manager'], true)) {
    $pdo->prepare("DELETE FROM store_inventory WHERE item_uuid=? AND school_uuid=?")->execute([safe_str($_POST['item_uuid']??''),$school_uuid]);
    $success_msg = 'Item removed.';
}
if (isset($_POST['action_pos_sale'])) {
    $student_uuid = safe_str($_POST['student_uuid'] ?? '');
    $student_name = safe_str($_POST['student_name'] ?? 'Walk-in Customer');
    $payment      = safe_str($_POST['payment_method'] ?? 'Cash / Ledger');
    $item_uuids   = $_POST['sale_item_uuid'] ?? [];
    $item_qtys    = $_POST['sale_item_qty']  ?? [];

    if (empty($item_uuids)) {
        $error_msg = 'Add at least one item to the sale.';
    } else {
        $total = 0; $summary = [];
        $pdo->beginTransaction();
        try {
            foreach ($item_uuids as $i => $iu) {
                $qty = max(1, safe_int($item_qtys[$i] ?? 1));
                $st = $pdo->prepare("SELECT item_name, unit_price, stock_quantity FROM store_inventory WHERE item_uuid=? AND school_uuid=? FOR UPDATE");
                $st->execute([$iu, $school_uuid]);
                $item = $st->fetch();
                if (!$item) continue;
                if ($item['stock_quantity'] < $qty) {
                    throw new Exception("Not enough stock for {$item['item_name']} (only {$item['stock_quantity']} left).");
                }
                $line_total = $item['unit_price'] * $qty;
                $total += $line_total;
                $summary[] = ['name' => $item['item_name'], 'qty' => $qty, 'unit_price' => $item['unit_price'], 'line_total' => $line_total];
                $pdo->prepare("UPDATE store_inventory SET stock_quantity = stock_quantity - ? WHERE item_uuid=? AND school_uuid=?")
                    ->execute([$qty, $iu, $school_uuid]);
            }
            if (empty($summary)) throw new Exception('No valid items in the sale.');

            $receipt_no = 'RCT-' . strtoupper(substr(uid(''), -8));
            $sale_uuid = uid('sale');
            $pdo->prepare("INSERT INTO store_pos_sales (sale_uuid,school_uuid,student_uuid,student_name,items_summary_json,total_amount,payment_method,receipt_number) VALUES (?,?,?,?,?,?,?,?)")
                ->execute([$sale_uuid,$school_uuid,$student_uuid ?: null,$student_name,json_encode($summary),$total,$payment,$receipt_no]);
            $pdo->commit();
            AuditLog::write($pdo,$school_uuid,$user_uuid,'store.sale',$sale_uuid,"Sold ₦" . number_format($total,2) . " to $student_name");
            $success_msg = "Sale complete! Receipt $receipt_no — ₦" . number_format($total, 2);
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_msg = safe_error('Sale failed', $e);
        }
    }
}
