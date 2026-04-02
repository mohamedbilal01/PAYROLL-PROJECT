<?php
session_start();
date_default_timezone_set('Asia/Kolkata');

// Security Check
if (!isset($_SESSION['role']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'hr')) { 
    header("Location: index.php"); exit(); 
}
include 'db.php';

$current_user_role = strtolower($_SESSION['role']);

$msg = "";
$msg_type = "";

// Handle Delete Action safely
if(isset($_POST['delete_id'])) {
    $del_id = (int)$_POST['delete_id'];
    if($del_id != $_SESSION['user_id']) {
        // Find if they are not admin first
        $check = $conn->query("SELECT role FROM employees WHERE id='$del_id'");
        if ($check && $check->num_rows > 0) {
            $emp = $check->fetch_assoc();
            
            // Replicate original checking behavior, but safely delete foreign keys first.
            if (strtolower(trim($emp['role'])) != 'admin') {
                // Pre-delete dependent tables to prevent constraint errors
                $conn->query("DELETE FROM attendance WHERE employee_id='$del_id'");
                $conn->query("DELETE FROM leave_requests WHERE employee_id='$del_id'");
                $conn->query("DELETE FROM payroll WHERE employee_id='$del_id'");
                
                // Now safely delete the employee
                if($conn->query("DELETE FROM employees WHERE id='$del_id'")) {
                    $msg = "Employee deleted successfully.";
                    $msg_type = "success";
                }
            }
        }
    }
}

// Handle Bulk Delete Action Safely
if(isset($_POST['bulk_delete']) && !empty($_POST['selected_ids'])) {
    $deleted_count = 0;
    foreach($_POST['selected_ids'] as $id) {
        $del_id = (int)$id;
        if($del_id != $_SESSION['user_id']) {
            $check = $conn->query("SELECT role FROM employees WHERE id='$del_id'");
            if ($check && $check->num_rows > 0) {
                $emp = $check->fetch_assoc();
                if (strtolower(trim($emp['role'])) != 'admin') {
                    $conn->query("DELETE FROM attendance WHERE employee_id='$del_id'");
                    $conn->query("DELETE FROM leave_requests WHERE employee_id='$del_id'");
                    $conn->query("DELETE FROM payroll WHERE employee_id='$del_id'");
                    if($conn->query("DELETE FROM employees WHERE id='$del_id'")) {
                        $deleted_count++;
                    }
                }
            }
        }
    }
    if ($deleted_count > 0) {
        $msg = "$deleted_count Employee(s) deleted successfully.";
        $msg_type = "success";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Manage Staff | NexusHR</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css"> 
    <style>
        /* Custom Premium Confirmation Modal */
        .delete-modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); display: none; align-items: center; justify-content: center; z-index: 9999; }
        .delete-modal { background: white; padding: 30px 40px; border-radius: 16px; text-align: center; max-width: 400px; width: 90%; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); transform: translateY(20px); opacity: 0; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .delete-modal.active { transform: translateY(0); opacity: 1; }
        .modal-icon { width: 60px; height: 60px; background: #fee2e2; color: #ef4444; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 24px; margin-bottom: 20px; }
        .modal-title { margin: 0 0 10px 0; font-size: 20px; font-weight: 800; color: #0f172a; }
        .modal-desc { margin: 0 0 25px 0; color: #64748b; font-size: 14px; line-height: 1.5; }
        .modal-actions { display: flex; gap: 15px; }
        .btn-cancel { flex: 1; padding: 12px; border-radius: 10px; border: 1px solid #cbd5e1; background: white; color: #475569; font-weight: 700; cursor: pointer; transition: 0.2s; }
        .btn-cancel:hover { background: #f8fafc; color: #0f172a; }
        .btn-confirm-delete { flex: 1; padding: 12px; border-radius: 10px; border: none; background: #ef4444; color: white; font-weight: 700; cursor: pointer; transition: 0.2s; box-shadow: 0 4px 6px -1px rgba(239, 68, 68, 0.2); }
        .btn-confirm-delete:hover { background: #dc2626; transform: translateY(-1px); box-shadow: 0 6px 8px -1px rgba(239, 68, 68, 0.3); }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <div>
                <h1 class="page-title">Employee Directory</h1>
                <p class="page-subtitle">Manage system access and employee records.</p>
            </div>
            <div style="display: flex; gap: 10px;">
                <a href="import_employees.php" class="btn btn-dark"><i class="fas fa-file-csv"></i> Import CSV</a>
                <a href="add_employee.php" class="btn btn-dark"><i class="fas fa-plus"></i> Add New Staff</a>
            </div>
        </div>

        <?php if(!empty($msg)): ?>
            <div style="padding:15px; border-radius:8px; margin-bottom:20px; font-weight:600; display:flex; align-items:center; gap:10px; <?php echo $msg_type == 'success' ? 'background:#f0fdf4; color:#15803d; border:1px solid #bbf7d0;' : 'background:#fef2f2; color:#991b1b; border:1px solid #fecaca;'; ?>">
                <i class="fas <?php echo $msg_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i> <?php echo $msg; ?>
            </div>
        <?php endif; ?>

        <div class="bento-card" style="padding: 10px 30px 30px 30px;">
            <form method="POST" action="manage_employees.php" id="bulkForm">
                <div style="display:flex; justify-content:space-between; align-items:center; padding: 15px 0;">
                    <div style="display:flex; align-items:center; gap: 20px;">
                        <div style="font-weight: 600; color: #64748b; font-size: 14px; min-width: 90px; border-right: 1px solid #e2e8f0; padding-right: 20px;">
                            <span id="selectedCount">0</span> selected
                        </div>
                        <div style="position: relative;">
                            <i class="fas fa-search" style="position: absolute; left: 16px; top: 12px; color: #94a3b8;"></i>
                            <input type="text" id="searchInput" onkeyup="searchTable()" placeholder="Quickly search staff..." style="width: 320px; padding: 10px 15px 10px 45px; border-radius: 9999px; border: 1px solid #cbd5e1; outline: none; font-size: 14px; font-family: Inter, sans-serif; transition: 0.2s;">
                        </div>
                    </div>
                    <button type="submit" name="bulk_delete" class="btn" style="background:#ef4444; color:white; opacity:0.5; pointer-events:none; transition:0.3s;" id="bulkDeleteBtn" onclick="return triggerBulkModal(event, this.form);">
                        <i class="fas fa-trash-alt"></i> Delete Selected
                    </button>
                </div>
            
                <table class="bento-table">
                    <thead>
                        <tr>
                            <th style="width: 40px;"><input type="checkbox" id="selectAllCheckbox" style="transform: scale(1.2); cursor:pointer;"></th>
                            <th>Name / Email</th>
                            <th>Role</th>
                            <th>Job Title</th>
                            <th>Salary</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    if ($current_user_role == 'admin') {
                        $query = "SELECT * FROM employees ORDER BY role ASC, name ASC";
                    } else {
                        $query = "SELECT * FROM employees WHERE role != 'admin' ORDER BY role ASC, name ASC";
                    }
                    
                    $emps = $conn->query($query);
                    
                    while($e = $emps->fetch_assoc()) {
                        $r = strtolower($e['role']);
                        
                        // --- AVATAR LOGIC (Images vs Initials) ---
                        $badge_class = 'badge-emp'; $av_bg = '#e0e7ff'; $av_col = '#3730a3';
                        if($r == 'admin') { $badge_class = 'badge-admin'; $av_bg = '#dcfce7'; $av_col = '#166534'; }
                        elseif($r == 'hr') { $badge_class = 'badge-hr'; $av_bg = '#fef08a'; $av_col = '#854d0e'; }

                        if (!empty($e['profile_image']) && file_exists('uploads/' . $e['profile_image'])) {
                            // Show real image
                            $avatar_display = "<img src='uploads/".$e['profile_image']."' style='width:100%; height:100%; border-radius:12px; object-fit:cover;'>";
                        } else {
                            // Show initials fallback
                            $words = explode(" ", $e['name']);
                            $initials = strtoupper(substr($words[0], 0, 1) . (isset($words[1]) ? substr($words[1], 0, 1) : ''));
                            $avatar_display = $initials;
                        }
                        
                        $join_date = (!empty($e['created_at']) && strtotime($e['created_at']) > 0) ? date('M Y', strtotime($e['created_at'])) : '-';
                        $salary_display = ($r == 'admin') ? '-' : '₹' . number_format($e['base_salary']);
                        
                        $is_disabled = ($e['id'] == $_SESSION['user_id'] || $r == 'admin');
                        $del_disabled = $is_disabled ? "disabled" : "";
                        $del_style = $is_disabled ? "background:#fee2e2; color:#fca5a5; padding:6px 12px; font-size:12px; pointer-events:none; cursor:not-allowed;" : "background:#fef2f2; color:#dc2626; padding:6px 12px; font-size:12px; cursor:pointer;";

                        echo "<tr>
                            <td>".(!$is_disabled ? "<input type='checkbox' name='selected_ids[]' value='".$e['id']."' class='employee-checkbox' style='transform: scale(1.2); cursor:pointer;'>" : "")."</td>
                            <td>
                                <div style='display:flex; align-items:center; gap:15px;'>
                                    <div style='width:45px; height:45px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-weight:700; background:$av_bg; color:$av_col;'>
                                        $avatar_display
                                    </div>
                                    <div>
                                        <div style='font-weight:700; color:#0f172a; margin-bottom:4px;'>".$e['name']."</div>
                                        <div style='font-size:12px; color:#64748b;'>".$e['email']."</div>
                                    </div>
                                </div>
                            </td>
                            <td><span class='badge $badge_class'>".$e['role']."</span></td>
                            <td>".$e['job_title']."</td>
                            <td style='font-weight:700;'>$salary_display</td>
                            <td>$join_date</td>
                            <td>
                                <div style='display:flex; gap:8px;'>
                                    <a href='edit_employee.php?id=".$e['id']."' class='btn' style='background:#f1f5f9; color:#475569; padding:6px 12px; font-size:12px; text-decoration:none;'><i class='fas fa-pen'></i> Edit</a>
                                    <form method='POST' action='manage_employees.php' style='margin:0;' onsubmit='return triggerDeleteModal(event, this);'>
                                        <input type='hidden' name='delete_id' value='".$e['id']."'>
                                        <button type='submit' class='btn' $del_disabled style='border:none; $del_style'><i class='fas fa-trash'></i> Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>";
                    }
                    ?>
                </tbody>
            </table>
            </form>
        </div>
    </div>
</div>

<!-- Premium Custom Modal Overlay -->
<div class="delete-modal-overlay" id="deleteModalOverlay">
    <div class="delete-modal" id="deleteModalBox">
        <div class="modal-icon"><i class="fas fa-exclamation-triangle"></i></div>
        <h3 class="modal-title" id="modalTitle">Delete Employee?</h3>
        <p class="modal-desc" id="modalDesc">Are you absolutely sure you want to delete this employee? This action will permanently remove all of their associated records and cannot be undone.</p>
        <div class="modal-actions">
            <button type="button" class="btn-cancel" onclick="closeDeleteModal()">Cancel</button>
            <button type="button" class="btn-confirm-delete" onclick="executeDelete()">Yes, Delete</button>
        </div>
    </div>
</div>

<script>
    let formToSubmit = null;
    let isBulkSubmit = false;

    // Checkbox Logic
    const selectAll = document.getElementById('selectAllCheckbox');
    const checkboxes = document.querySelectorAll('.employee-checkbox');
    const selectedCount = document.getElementById('selectedCount');
    const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');

    function updateBulkUI() {
        const checked = document.querySelectorAll('.employee-checkbox:checked').length;
        selectedCount.textContent = checked;
        if(checked > 0) {
            bulkDeleteBtn.style.opacity = '1';
            bulkDeleteBtn.style.pointerEvents = 'auto';
            bulkDeleteBtn.style.boxShadow = '0 4px 10px rgba(239, 68, 68, 0.3)';
        } else {
            bulkDeleteBtn.style.opacity = '0.5';
            bulkDeleteBtn.style.pointerEvents = 'none';
            bulkDeleteBtn.style.boxShadow = 'none';
            selectAll.checked = false;
        }
    }

    if(selectAll) {
        selectAll.addEventListener('change', function(e) {
            checkboxes.forEach(cb => { cb.checked = e.target.checked; });
            updateBulkUI();
        });
    }

    checkboxes.forEach(cb => {
        cb.addEventListener('change', function() {
            const allChecked = document.querySelectorAll('.employee-checkbox:checked').length === checkboxes.length;
            selectAll.checked = allChecked;
            updateBulkUI();
        });
    });

    // Custom Modal Logic
    function triggerDeleteModal(e, form) {
        e.preventDefault();
        formToSubmit = form;
        isBulkSubmit = false;
        document.getElementById('modalTitle').textContent = 'Delete Employee?';
        document.getElementById('modalDesc').textContent = 'Are you absolutely sure you want to delete this specific employee? This action is permanent.';
        showModal();
    }

    function triggerBulkModal(e, form) {
        e.preventDefault();
        const count = document.querySelectorAll('.employee-checkbox:checked').length;
        formToSubmit = form;
        isBulkSubmit = true;
        document.getElementById('modalTitle').textContent = `Delete ${count} Employee(s)?`;
        document.getElementById('modalDesc').textContent = `Are you sure you want to completely erase these ${count} selected records? This cannot be undone.`;
        showModal();
    }

    function showModal() {
        const overlay = document.getElementById('deleteModalOverlay');
        const box = document.getElementById('deleteModalBox');
        overlay.style.display = 'flex';
        setTimeout(() => { box.classList.add('active'); }, 10);
    }

    function closeDeleteModal() {
        const overlay = document.getElementById('deleteModalOverlay');
        const box = document.getElementById('deleteModalBox');
        box.classList.remove('active');
        setTimeout(() => { overlay.style.display = 'none'; formToSubmit = null; }, 300);
    }

    function executeDelete() {
        if(formToSubmit) {
            if(isBulkSubmit) {
                // Must append this because the button's name wasn't submitted via JS
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'bulk_delete';
                hiddenInput.value = '1';
                formToSubmit.appendChild(hiddenInput);
            }
            formToSubmit.submit();
        }
    }

    // Real-time Visual Search Filter
    function searchTable() {
        const input = document.getElementById("searchInput");
        const filter = input.value.toLowerCase();
        const tbody = document.querySelector(".bento-table tbody");
        const tr = tbody.querySelectorAll("tr");

        for (let i = 0; i < tr.length; i++) {
            // Find name/email td(1), role td(2), job td(3)
            const tdArray = [tr[i].getElementsByTagName("td")[1], tr[i].getElementsByTagName("td")[2], tr[i].getElementsByTagName("td")[3]];
            let found = false;
            
            for(let j = 0; j < tdArray.length; j++) {
                if(tdArray[j]) {
                    const txtValue = tdArray[j].textContent || tdArray[j].innerText;
                    if(txtValue.toLowerCase().indexOf(filter) > -1) {
                        found = true;
                        break;
                    }
                }
            }
            tr[i].style.display = found ? "" : "none";
        }
    }

    // Add glowing focus to search
    const searchInput = document.getElementById('searchInput');
    searchInput.addEventListener('focus', function() { this.style.borderColor = '#4361ee'; this.style.boxShadow = '0 0 0 3px rgba(67, 97, 238, 0.1)'; });
    searchInput.addEventListener('blur', function() { this.style.borderColor = '#cbd5e1'; this.style.boxShadow = 'none'; });
</script>

</body>
</html>