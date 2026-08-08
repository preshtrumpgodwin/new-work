<?php
/**
 * Platform Manager - Shared Configuration
 */

// Time-based theme detection
function getThemeMode($pdo, $session_theme = 'auto') {
    if ($session_theme === 'auto') {
        $hour = (int)date('H');
        return ($hour >= 6 && $hour < 18) ? 'light' : 'dark';
    }
    return $session_theme;
}

// Platform branding
$platform_name = 'Zetaphase EduCloud';
$platform_subdomain = 'platform.zetaphase.com.ng';
$platform_logo = '../logo.jpeg';

// Success/error messages from session
$success_msg = $_SESSION['platform_success'] ?? '';
$error_msg = $_SESSION['platform_error'] ?? '';
unset($_SESSION['platform_success'], $_SESSION['platform_error']);

// Stats
$stats = [];
try {
    $stats['active_schools'] = (int)$pdo->query("SELECT COUNT(*) FROM schools WHERE status='Active'")->fetchColumn();
    $stats['pending_reqs'] = (int)$pdo->query("SELECT COUNT(*) FROM onboarding_requests WHERE status='Pending'")->fetchColumn();
    $stats['total_revenue'] = (float)$pdo->query("SELECT SUM(monthly_fee) FROM schools WHERE status='Active'")->fetchColumn();
} catch (Exception $e) {
    $stats = ['active_schools' => 0, 'pending_reqs' => 0, 'total_revenue' => 0];
}

// Billing automation: calculate next invoice date
function getNextInvoiceDate($school_uuid, $pdo) {
    try {
        // Get last invoice
        $stmt = $pdo->prepare("SELECT due_date, status FROM school_invoices 
                               WHERE school_uuid = ? ORDER BY due_date DESC LIMIT 1");
        $stmt->execute([$school_uuid]);
        $last = $stmt->fetch();
        
        if (!$last) {
            // First invoice: 10 days from now
            return date('Y-m-d', strtotime('+10 days'));
        }
        
        if ($last['status'] === 'Paid') {
            // Next invoice: 30 days from last due date
            return date('Y-m-d', strtotime($last['due_date'] . ' +30 days'));
        }
        
        // Overdue: 7 days after last due date
        return date('Y-m-d', strtotime($last['due_date'] . ' +7 days'));
    } catch (Exception $e) {
        return date('Y-m-d', strtotime('+10 days'));
    }
}

// Check for auto-invoice generation
function checkAndGenerateInvoices($pdo) {
    $today = date('Y-m-d');
    $seven_days_ago = date('Y-m-d', strtotime('-7 days'));
    
    try {
        // Get all active schools
        $schools = $pdo->query("SELECT school_uuid, plan, billing_cycle, monthly_fee FROM schools WHERE status='Active'")->fetchAll();
        
        foreach ($schools as $school) {
            // Check if there's an unpaid invoice overdue
            $overdue = $pdo->prepare("SELECT COUNT(*) FROM school_invoices 
                                      WHERE school_uuid = ? AND status = 'Unpaid' AND due_date < ?");
            $overdue->execute([$school['school_uuid'], $today]);
            
            if ($overdue->fetchColumn() > 0) {
                // Already has overdue invoice - skip
                continue;
            }
            
            // Check if there's a pending invoice due soon
            $pending = $pdo->prepare("SELECT COUNT(*) FROM school_invoices 
                                      WHERE school_uuid = ? AND status = 'Unpaid' AND due_date >= ?");
            $pending->execute([$school['school_uuid'], $today]);
            
            if ($pending->fetchColumn() > 0) {
                // Already has pending invoice
                continue;
            }
            
            // Check if last invoice was paid more than 30 days ago
            $last_paid = $pdo->prepare("SELECT due_date FROM school_invoices 
                                        WHERE school_uuid = ? AND status = 'Paid' 
                                        ORDER BY due_date DESC LIMIT 1");
            $last_paid->execute([$school['school_uuid']]);
            $last = $last_paid->fetch();
            
            if ($last) {
                $next_due = date('Y-m-d', strtotime($last['due_date'] . ' +30 days'));
                if ($next_due <= $today) {
                    // Generate new invoice
                    $invoice_no = 'INV-ZETA-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                    $invoice_uuid = 'inv-' . uniqid();
                    
                    $ins = $pdo->prepare("
                        INSERT INTO school_invoices (invoice_uuid, school_uuid, invoice_no, plan, billing_cycle, amount, status, due_date)
                        VALUES (?, ?, ?, ?, ?, ?, 'Unpaid', ?)
                    ");
                    $ins->execute([
                        $invoice_uuid,
                        $school['school_uuid'],
                        $invoice_no,
                        $school['plan'],
                        $school['billing_cycle'],
                        $school['monthly_fee'],
                        $next_due
                    ]);
                    
                    // Add reminder
                    $rem = $pdo->prepare("
                        INSERT INTO subscription_reminders (reminder_uuid, school_uuid, message, date)
                        VALUES (?, ?, ?, ?)
                    ");
                    $rem->execute([
                        'rem-' . uniqid(),
                        $school['school_uuid'],
                        "Invoice $invoice_no (₦" . number_format($school['monthly_fee'], 2) . ") has been generated. Due date: $next_due.",
                        date('Y-m-d')
                    ]);
                }
            } else {
                // No previous invoice - first one
                $first_due = date('Y-m-d', strtotime('+10 days'));
                $invoice_no = 'INV-ZETA-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                $invoice_uuid = 'inv-' . uniqid();
                
                $ins = $pdo->prepare("
                    INSERT INTO school_invoices (invoice_uuid, school_uuid, invoice_no, plan, billing_cycle, amount, status, due_date)
                    VALUES (?, ?, ?, ?, ?, ?, 'Unpaid', ?)
                ");
                $ins->execute([
                    $invoice_uuid,
                    $school['school_uuid'],
                    $invoice_no,
                    $school['plan'],
                    $school['billing_cycle'],
                    $school['monthly_fee'],
                    $first_due
                ]);
            }
        }
    } catch (Exception $e) {
        error_log('Auto-invoice generation error: ' . $e->getMessage());
    }
}

// Run auto-invoice check on every page load (once per day)
$today = date('Y-m-d');
$last_check = $_SESSION['billing_last_check'] ?? '';
if ($last_check !== $today) {
    checkAndGenerateInvoices($pdo);
    $_SESSION['billing_last_check'] = $today;
}