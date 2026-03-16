<?php
/**
 * register.php — Student Self-Registration
 * Hostel Management System
 */
$pageTitle = 'Student Registration';
// We don't require login, but we include header which might show login/register links
require_once __DIR__ . '/includes/header.php';

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    roleDashboard($_SESSION['role']);
}
?>

<div class="auth-wrapper" style="max-width: 600px; margin: 40px auto; padding: 20px;">
    <div class="auth-card" style="background:#fff; border-radius:12px; padding:30px; box-shadow:0 4px 6px rgba(0,0,0,0.05); border:1px solid #e2e8f0;">
        
        <div class="auth-header" style="text-align:center; margin-bottom:20px;">
            <h2 style="color:#1e293b; margin:0 0 10px 0;">Student Registration</h2>
            <p style="color:#64748b; margin:0;">Apply for hostel accommodation. An administrator will review your request.</p>
        </div>

        <form method="POST" action="<?= BASE_URL ?>actions/register_action.php">
            
            <h4 style="border-bottom:1px solid #e2e8f0; padding-bottom:5px; margin-bottom:15px; color:#3b82f6;">Personal Details</h4>
            
            <div class="form-grid" style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-bottom:15px;">
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label>Full Name <span style="color:red">*</span></label>
                    <input type="text" name="name" class="form-control" required placeholder="John Doe">
                </div>
                
                <div class="form-group">
                    <label>Email Address <span style="color:red">*</span></label>
                    <input type="email" name="email" class="form-control" required placeholder="john@example.com">
                </div>
                
                <div class="form-group">
                    <label>Phone Number <span style="color:red">*</span></label>
                    <input type="text" name="phone" class="form-control" required placeholder="+1 234 567 8900">
                </div>
                
                <div class="form-group">
                    <label>Date of Birth <span style="color:red">*</span></label>
                    <input type="date" name="dob" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Gender <span style="color:red">*</span></label>
                    <select name="gender" class="form-control" required>
                        <option value="">Select Gender</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                        <option value="other">Other</option>
                    </select>
                </div>
            </div>

            <h4 style="border-bottom:1px solid #e2e8f0; padding-bottom:5px; margin-bottom:15px; color:#3b82f6;">Account Setup</h4>
            
            <div class="form-grid" style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-bottom:15px;">
                <div class="form-group">
                    <label>Password <span style="color:red">*</span></label>
                    <input type="password" name="password" class="form-control" required placeholder="Create a password">
                </div>
                <div class="form-group">
                    <label>Confirm Password <span style="color:red">*</span></label>
                    <input type="password" name="confirm_password" class="form-control" required placeholder="Confirm your password">
                </div>
            </div>

            <h4 style="border-bottom:1px solid #e2e8f0; padding-bottom:5px; margin-bottom:15px; color:#3b82f6;">Emergency Contact</h4>
            
            <div class="form-grid" style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-bottom:15px;">
                <div class="form-group">
                    <label>Guardian Name <span style="color:red">*</span></label>
                    <input type="text" name="guardian_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Guardian Phone <span style="color:red">*</span></label>
                    <input type="text" name="guardian_phone" class="form-control" required>
                </div>
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label>Home Address <span style="color:red">*</span></label>
                    <textarea name="address" class="form-control" rows="2" required></textarea>
                </div>
            </div>

            <h4 style="border-bottom:1px solid #e2e8f0; padding-bottom:5px; margin-bottom:15px; color:#3b82f6;">Accommodation Preference</h4>
            
            <div class="form-group" style="margin-bottom:25px;">
                <label>Room Preference <span style="color:red">*</span></label>
                <select name="room_preference" class="form-control" required>
                    <option value="">Select Room Type</option>
                    <option value="single">Single Room</option>
                    <option value="double">Double Room</option>
                    <option value="triple">Triple Room</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%; padding:12px; font-size:1.1rem; border-radius:8px;">Submit Application</button>
            
            <div style="text-align:center; margin-top:15px; color:#64748b; font-size:0.95rem;">
                Already have an account? <a href="<?= BASE_URL ?>login.php" style="color:#3b82f6; text-decoration:none; font-weight:600;">Log In</a>
            </div>
            
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
