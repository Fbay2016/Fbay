<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
header('Content-Type: application/json');

// Set default timezone for PHP functions globally
date_default_timezone_set('Asia/Kolkata');

// Database Configuration
$host = 'sql102.infinityfree.com';
$db   = 'if0_42376779_fbay';
$user = 'if0_42376779';
$pass = 'Fbay2026';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Force MySQL connection to use Asia/Kolkata (+05:30) timezone for NOW() and timestamps
    $pdo->exec("SET time_zone = '+05:30'");

} catch (\PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'includes/PHPMailer/src/Exception.php';
require 'includes/PHPMailer/src/PHPMailer.php';
require 'includes/PHPMailer/src/SMTP.php';

$data = json_decode(file_get_contents('php://input'), true);

// Fallback to $_POST if Content-Type is application/x-www-form-urlencoded
if (empty($data)) {
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    $data = $_POST;
} else {
    $action = $data['action'] ?? $_GET['action'] ?? '';
}

// --- 1. CHECK SPONSOR ACTION ---
if ($action === 'check_sponsor') {
    $sponsor = trim($data['sponsor'] ?? '');
    if (empty($sponsor)) {
        echo json_encode(['success' => false]);
        exit;
    }

    $stmt = $pdo->prepare("SELECT full_name FROM users WHERE email = ? OR referral_code = ?");
    $stmt->execute([$sponsor, $sponsor]);
    $sponsorUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($sponsorUser) {
        echo json_encode(['success' => true, 'sponsor_name' => $sponsorUser['full_name']]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}

// --- 2. SEND OTP ACTION (For Signup, Signin, or Password Change) ---
if ($action === 'send_otp' || $action === 'send_login_otp' || $action === 'send_password_otp') {
    if ($action === 'send_password_otp') {
        if (!isset($_SESSION['user_email'])) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized session.']);
            exit;
        }
        $email = $_SESSION['user_email'];
    } else {
        $email = trim($data['email'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Invalid email address']);
            exit;
        }

        if ($action === 'send_otp') {
            // Registration check
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => false, 'message' => 'Email is already registered. Please sign in.']);
                exit;
            }
        } else {
            // Signin check
            $password = trim($data['password'] ?? '');
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user || $password !== $user['password']) {
                echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
                exit;
            }
        }
    }

    $otp = rand(100000, 999999);
    $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    $stmt = $pdo->prepare("DELETE FROM otps WHERE email = ?");
    $stmt->execute([$email]);

    $stmt = $pdo->prepare("INSERT INTO otps (email, otp, expires_at) VALUES (?, ?, ?)");
    $stmt->execute([$email, $otp, $expires_at]);

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com'; 
        $mail->SMTPAuth = true;
        $mail->Username = 'fbayinfyclick@gmail.com'; 
        $mail->Password = 'qaay meew eiin itvu'; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        $mail->setFrom('fbayinfyclick@gmail.com', 'Fbay Official');
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'Your Secure Verification Code - Fbay';
        $mail->Body = '
        <!DOCTYPE html>
        <html>
        <head><meta charset="UTF-8"><title>Verification OTP</title></head>
        <body style="margin: 0; padding: 0; background-color: #0f172a; font-family: \'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif;">
            <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px; background-color: #1e293b; margin: 40px auto; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 25px rgba(0,0,0,0.3);">
                <tr>
                    <td align="center" style="background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); padding: 35px 20px;">
                        <h1 style="color: #ffffff; margin: 0; font-size: 26px; font-weight: 700; letter-spacing: 1px;">Fbay Security</h1>
                        <p style="color: #93c5fd; margin: 8px 0 0 0; font-size: 14px;">Account Verification Process</p>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 35px 30px; color: #cbd5e1;">
                        <p style="margin: 0 0 15px 0; font-size: 16px; color: #f8fafc;">Hello,</p>
                        <p style="margin: 0 0 25px 0; font-size: 14px; line-height: 1.6; color: #94a3b8;">Please use the verification code below to proceed with your action.</p>
                        <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #0f172a; border: 1px solid #334155; border-radius: 12px; margin-bottom: 25px;">
                            <tr>
                                <td align="center" style="padding: 20px;">
                                    <span style="font-size: 32px; font-weight: 800; letter-spacing: 8px; color: #38bdf8; display: inline-block;">' . $otp . '</span>
                                </td>
                            </tr>
                        </table>
                        <p style="margin: 0; font-size: 13px; color: #64748b; text-align: center;">This verification code will expire in <strong>10 minutes</strong>.</p>
                    </td>
                </tr>
                <tr>
                    <td align="center" style="background-color: #0b0f19; padding: 20px; border-top: 1px solid #334155;">
                        <p style="margin: 0; font-size: 12px; color: #64748b;">&copy; 2016-26 Fbay. All rights reserved.</p>
                    </td>
                </tr>
            </table>
        </body>
        </html>';
        
        $mail->send();
        echo json_encode(['success' => true, 'message' => 'OTP sent successfully to your email.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to send email: ' . $mail->ErrorInfo]);
    }
    exit;
}

// --- 3. SIGN UP ACTION ---
if ($action === 'signup') {
    $fullName = trim($data['fullName'] ?? '');
    $email = trim($data['email'] ?? '');
    $password = trim($data['password'] ?? ''); 
    $sponsorCode = trim($data['sponsorCode'] ?? '');
    $otp = trim($data['otp'] ?? '');

    if (empty($sponsorCode)) {
        echo json_encode(['success' => false, 'message' => 'Sponsor code is mandatory.']);
        exit;
    }

    $stmtSponsor = $pdo->prepare("SELECT full_name FROM users WHERE email = ? OR referral_code = ?");
    $stmtSponsor->execute([$sponsorCode, $sponsorCode]);
    $sData = $stmtSponsor->fetch(PDO::FETCH_ASSOC);

    if (!$sData) {
        echo json_encode(['success' => false, 'message' => 'Invalid sponsor code.']);
        exit;
    }
    $sponsorName = $sData['full_name'];

    $stmt = $pdo->prepare("SELECT * FROM otps WHERE email = ? AND otp = ? AND expires_at > NOW()");
    $stmt->execute([$email, $otp]);
    
    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired OTP.']);
        exit;
    }

    $referralCode = 'FBAY' . rand(10000, 99999);

    try {
        // Balance set to 50.00 upon registration
        $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, balance, sponsor, referral_code, created_at) VALUES (?, ?, ?, 50.00, ?, ?, NOW())");
        $stmt->execute([$fullName, $email, $password, $sponsorCode, $referralCode]);

        $stmt = $pdo->prepare("DELETE FROM otps WHERE email = ?");
        $stmt->execute([$email]);

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com'; 
            $mail->SMTPAuth = true;
            $mail->Username = 'fbayinfyclick@gmail.com'; 
            $mail->Password = 'qaay meew eiin itvu'; 
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
            
            $mail->setFrom('fbayinfyclick@gmail.com', 'Fbay Official');
            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = 'Welcome to Fbay! Registration Successful';
            $mail->Body = '
            <!DOCTYPE html>
            <html>
            <head><meta charset="UTF-8"><title>Welcome to Fbay</title></head>
            <body style="margin: 0; padding: 0; background-color: #0f172a; font-family: \'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif;">
                <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px; background-color: #1e293b; margin: 40px auto; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 25px rgba(0,0,0,0.3);">
                    <tr>
                        <td align="center" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); padding: 40px 20px;">
                            <h1 style="color: #ffffff; margin: 0; font-size: 28px; font-weight: 800; letter-spacing: 1px;">Welcome To Fbay!</h1>
                            <p style="color: #a7f3d0; margin: 8px 0 0 0; font-size: 15px;">Your Account is Successfully Created with Rs. 50 Bonus</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 35px 30px; color: #cbd5e1;">
                            <p style="margin: 0 0 10px 0; font-size: 16px; color: #f8fafc;">Hello <strong>' . htmlspecialchars($fullName) . '</strong>,</p>
                            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #0f172a; border: 1px solid #334155; border-radius: 12px; margin-bottom: 25px;">
                                <tr>
                                    <td style="padding: 20px;">
                                        <table border="0" cellpadding="6" cellspacing="0" width="100%">
                                            <tr><td width="35%" style="font-size: 13px; color: #64748b; font-weight: 600;">Full Name:</td><td width="65%" style="font-size: 14px; color: #f8fafc; font-weight: 500;">' . htmlspecialchars($fullName) . '</td></tr>
                                            <tr><td style="font-size: 13px; color: #64748b; font-weight: 600;">Email Address:</td><td style="font-size: 14px; color: #f8fafc; font-weight: 500;">' . htmlspecialchars($email) . '</td></tr>
                                            <tr><td style="font-size: 13px; color: #64748b; font-weight: 600;">Password:</td><td style="font-size: 14px; color: #f43f5e; font-weight: 600;">' . htmlspecialchars($password) . '</td></tr>
                                            <tr><td style="font-size: 13px; color: #64748b; font-weight: 600;">Initial Balance:</td><td style="font-size: 14px; color: #34d399; font-weight: 700;">Rs. 50.00</td></tr>
                                            <tr><td style="font-size: 13px; color: #64748b; font-weight: 600;">Referral Code:</td><td style="font-size: 14px; color: #38bdf8; font-weight: 700;">' . htmlspecialchars($referralCode) . '</td></tr>
                                            <tr><td style="font-size: 13px; color: #64748b; font-weight: 600;">Sponsor:</td><td style="font-size: 14px; color: #34d399; font-weight: 600;">' . htmlspecialchars($sponsorName) . '</td></tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="background-color: #0b0f19; padding: 20px; border-top: 1px solid #334155;">
                            <p style="margin: 0; font-size: 12px; color: #64748b;">&copy; 2016-26 Fbay. All rights reserved.</p>
                        </td>
                    </tr>
                </table>
            </body>
            </html>';
            $mail->send();
        } catch (Exception $e) {}

        echo json_encode(['success' => true, 'message' => 'Registration successful! Rs. 50 credited. You can now sign in.']);
    } catch (\PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Registration failed. Email might already exist.']);
    }
    exit;
}


// --- 4. SIGN IN ACTION ---
if ($action === 'signin') {
    $email = trim($data['email'] ?? '');
    $password = trim($data['password'] ?? '');
    $otp = trim($data['otp'] ?? '');

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || $password !== $user['password']) {
        echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
        exit;
    }

    $stmtOtp = $pdo->prepare("SELECT * FROM otps WHERE email = ? AND otp = ? AND expires_at > NOW()");
    $stmtOtp->execute([$email, $otp]);
    
    if ($stmtOtp->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired Login OTP.']);
        exit;
    }

    $stmtDelete = $pdo->prepare("DELETE FROM otps WHERE email = ?");
    $stmtDelete->execute([$email]);

    $_SESSION['user_email'] = $user['email'];
    echo json_encode(['success' => true, 'message' => 'Login successful!', 'user' => $user['full_name']]);
    exit;
}

// --- 5. GET BANK DETAILS ---
if ($action === 'get_bank') {
    if (!isset($_SESSION['user_email'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    $stmt = $pdo->prepare("SELECT holder_name, bank_name, account_number, ifsc_code FROM user_banks WHERE email = ?");
    $stmt->execute([$_SESSION['user_email']]);
    $bank = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'bank' => $bank]);
    exit;
}

// --- 6. SAVE BANK DETAILS ---
if ($action === 'save_bank') {
    if (!isset($_SESSION['user_email'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    $holder = trim($data['holder_name'] ?? '');
    $bankName = trim($data['bank_name'] ?? '');
    $accNo = trim($data['account_number'] ?? '');
    $ifsc = trim($data['ifsc_code'] ?? '');
    $email = $_SESSION['user_email'];

    $stmt = $pdo->prepare("SELECT id FROM user_banks WHERE email = ?");
    $stmt->execute([$email]);
    
    if ($stmt->fetch()) {
        // Added updated_at = NOW() to update the timestamp on modification
        $update = $pdo->prepare("UPDATE user_banks SET holder_name = ?, bank_name = ?, account_number = ?, ifsc_code = ?, created_at = NOW() WHERE email = ?");
        $update->execute([$holder, $bankName, $accNo, $ifsc, $email]);
    } else {
        // Added created_at/updated_at with NOW() for new records
        $insert = $pdo->prepare("INSERT INTO user_banks (email, holder_name, bank_name, account_number, ifsc_code, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $insert->execute([$email, $holder, $bankName, $accNo, $ifsc]);
    }
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com'; 
        $mail->SMTPAuth = true;
        $mail->Username = 'fbayinfyclick@gmail.com'; 
        $mail->Password = 'qaay meew eiin itvu'; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        $mail->setFrom('fbayinfyclick@gmail.com', 'Fbay Official');
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'Bank Card Bound Successfully - Fbay';
        $mail->Body = '
        <!DOCTYPE html>
        <html>
        <head><meta charset="UTF-8"><title>Bank Details Updated</title></head>
        <body style="margin: 0; padding: 0; background-color: #0f172a; font-family: \'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif;">
            <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px; background-color: #1e293b; margin: 40px auto; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 25px rgba(0,0,0,0.3);">
                <tr>
                    <td align="center" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); padding: 35px 20px;">
                        <h1 style="color: #ffffff; margin: 0; font-size: 24px; font-weight: 700;">Bank Card Bound</h1>
                        <p style="color: #a7f3d0; margin: 8px 0 0 0; font-size: 14px;">Your payout channel is updated</p>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 35px 30px; color: #cbd5e1;">
                        <p style="font-size: 16px; color: #f8fafc;">Hello,</p>
                        <p style="font-size: 14px; color: #94a3b8;">Your bank account details have been successfully bound to your Fbay account:</p>
                        <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #0f172a; border: 1px solid #334155; border-radius: 12px; margin-top: 15px;">
                            <tr>
                                <td style="padding: 20px;">
                                    <table border="0" cellpadding="6" cellspacing="0" width="100%">
                                        <tr><td width="40%" style="color: #64748b; font-size: 13px;">Holder Name:</td><td width="60%" style="color: #f8fafc; font-size: 14px;">' . htmlspecialchars($holder) . '</td></tr>
                                        <tr><td style="color: #64748b; font-size: 13px;">Bank Name:</td><td style="color: #f8fafc; font-size: 14px;">' . htmlspecialchars($bankName) . '</td></tr>
                                        <tr><td style="color: #64748b; font-size: 13px;">Account Number:</td><td style="color: #38bdf8; font-size: 14px;">' . htmlspecialchars($accNo) . '</td></tr>
                                        <tr><td style="color: #64748b; font-size: 13px;">IFSC Code:</td><td style="color: #f43f5e; font-size: 14px;">' . htmlspecialchars($ifsc) . '</td></tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td align="center" style="background-color: #0b0f19; padding: 20px; border-top: 1px solid #334155;">
                        <p style="margin: 0; font-size: 12px; color: #64748b;">&copy; 2016-26 Fbay. All rights reserved.</p>
                    </td>
                </tr>
            </table>
        </body>
        </html>';
        $mail->send();
    } catch (Exception $e) {}

    echo json_encode(['success' => true, 'message' => 'Bank details saved successfully.']);
    exit;
}


// --- SAVE / UPDATE PAN DETAILS ---
if ($action === 'save_pan') {
    if (!isset($_SESSION['user_email'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    $pan = trim($data['pan'] ?? '');
    $email = $_SESSION['user_email'];

    // Strict Server-side PAN validation
    if (!preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', $pan)) {
        echo json_encode(['success' => false, 'message' => 'Invalid PAN format.']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id FROM user_banks WHERE email = ?");
    $stmt->execute([$email]);
    
    if ($stmt->fetch()) {
        // Record exists, update only the PAN column
        $update = $pdo->prepare("UPDATE user_banks SET pan = ?, created_at = NOW() WHERE email = ?");
        $update->execute([$pan, $email]);
    } else {
        // Record does not exist, insert new row with email and pan
        $insert = $pdo->prepare("INSERT INTO user_banks (email, pan, created_at) VALUES (?, ?, NOW())");
        $insert->execute([$email, $pan]);
    }

    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com'; 
        $mail->SMTPAuth = true;
        $mail->Username = 'fbayinfyclick@gmail.com'; 
        $mail->Password = 'qaay meew eiin itvu'; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        $mail->setFrom('fbayinfyclick@gmail.com', 'Fbay Official');
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'PAN Card Updated Successfully - Fbay';
        $mail->Body = '
        <!DOCTYPE html>
        <html>
        <head><meta charset="UTF-8"><title>PAN Details Updated</title></head>
        <body style="margin: 0; padding: 0; background-color: #0f172a; font-family: \'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif;">
            <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px; background-color: #1e293b; margin: 40px auto; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 25px rgba(0,0,0,0.3);">
                <tr>
                    <td align="center" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); padding: 35px 20px;">
                        <h1 style="color: #ffffff; margin: 0; font-size: 24px; font-weight: 700;">KYC PAN Updated</h1>
                        <p style="color: #a7f3d0; margin: 8px 0 0 0; font-size: 14px;">Your compliance verification channel</p>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 35px 30px; color: #cbd5e1;">
                        <p style="font-size: 16px; color: #f8fafc;">Hello,</p>
                        <p style="font-size: 14px; color: #94a3b8;">Your PAN card details have been successfully updated and bound to your Fbay account:</p>
                        <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #0f172a; border: 1px solid #334155; border-radius: 12px; margin-top: 15px;">
                            <tr>
                                <td style="padding: 20px;">
                                    <table border="0" cellpadding="6" cellspacing="0" width="100%">
                                        <tr><td width="40%" style="color: #64748b; font-size: 13px;">PAN Number:</td><td width="60%" style="color: #38bdf8; font-size: 14px; font-weight: bold; letter-spacing: 1px;">' . htmlspecialchars($pan) . '</td></tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td align="center" style="background-color: #0b0f19; padding: 20px; border-top: 1px solid #334155;">
                        <p style="margin: 0; font-size: 12px; color: #64748b;">&copy; 2016-26 Fbay. All rights reserved.</p>
                    </td>
                </tr>
            </table>
        </body>
        </html>';
        $mail->send();
    } catch (Exception $e) {}

    echo json_encode(['success' => true, 'message' => 'PAN updated successfully.']);
    exit;
}

     // --- 7. GET TEAM REPORT (Dynamic up to 10 Levels) ---
if ($action === 'get_team') {
    if (!isset($_SESSION['user_email'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    $level = isset($_GET['level']) ? (int)$_GET['level'] : 1;
    $maxLevel = 10;
    if ($level < 1) $level = 1;
    if ($level > $maxLevel) $level = $maxLevel;

    $stmt = $pdo->prepare("SELECT referral_code FROM users WHERE email = ?");
    $stmt->execute([$_SESSION['user_email']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && !empty($user['referral_code'])) {
        $currentSponsors = [$user['referral_code']];
        $team = [];

        // Loop dynamically up to the requested level (max 10)
        for ($i = 1; $i <= $level; $i++) {
            if (empty($currentSponsors)) {
                $team = [];
                break;
            }

            $placeholders = implode(',', array_fill(0, count($currentSponsors), '?'));
            
            if ($i === $level) {
                // Target level reach ho gaya, toh saare details fetch karo
                $stmt = $pdo->prepare("SELECT full_name, email, referral_code, created_at FROM users WHERE sponsor IN ($placeholders)");
                $stmt->execute($currentSponsors);
                $team = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                // Next level ke liye sponsors collect karo
                $stmt = $pdo->prepare("SELECT referral_code FROM users WHERE sponsor IN ($placeholders)");
                $stmt->execute($currentSponsors);
                $currentSponsors = $stmt->fetchAll(PDO::FETCH_COLUMN);
            }
        }

        // Helper function to check active plan status
        $fetchTeamWithStatus = function($usersData) use ($pdo) {
            $formattedTeam = [];
            foreach ($usersData as $member) {
                $planStmt = $pdo->prepare("SELECT status FROM user_plans WHERE email = ? AND status = 'Active' LIMIT 1");
                $planStmt->execute([$member['email']]);
                $activePlan = $planStmt->fetch(PDO::FETCH_ASSOC);

                $member['plan_status'] = $activePlan ? 'Active' : 'Inactive';
                $formattedTeam[] = $member;
            }
            return $formattedTeam;
        };

        // Attach plan status to each team member
        $team = $fetchTeamWithStatus($team);

        echo json_encode(['success' => true, 'team' => $team]);
    } else {
        echo json_encode(['success' => true, 'team' => []]);
    }
    exit;
}
  

// --- 8. CHANGE PASSWORD ---
if ($action === 'change_password') {
    if (!isset($_SESSION['user_email'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    $email = $_SESSION['user_email'];
    $newPass = trim($data['new_password'] ?? '');
    $enteredOtp = trim($data['otp'] ?? '');

    $stmtOtp = $pdo->prepare("SELECT * FROM otps WHERE email = ? AND otp = ? AND expires_at > NOW()");
    $stmtOtp->execute([$email, $enteredOtp]);
    
    if ($stmtOtp->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired OTP.']);
        exit;
    }

    $update = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
    $update->execute([$newPass, $email]);

    $stmtDelete = $pdo->prepare("DELETE FROM otps WHERE email = ?");
    $stmtDelete->execute([$email]);

    echo json_encode(['success' => true, 'message' => 'Password changed successfully.']);
    exit;
}

// --- 9. SUBMIT DEPOSIT ACTION ---
if ($action === 'submit_deposit') {
    if (!isset($_SESSION['user_email'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

        // Set timezone to Asia/Kolkata
    date_default_timezone_set('Asia/Kolkata');
    
    // Debug current time values (Remove comment slashes to test if needed)
    // error_log("Server Date/Time: " . date('Y-m-d H:i:s'));

    $currentHour = (int)date('G'); // 'G' gives 24-hour format without leading zeros (0-23)
    $currentMinute = (int)date('i');
    $currentTimeDecimal = $currentHour + ($currentMinute / 60);

    // Check Deposit Time: 10:00 AM (10.0) to 10:00 PM (22.0)
    if ($currentTimeDecimal < 10.0 || $currentTimeDecimal >= 22.0) {
        echo json_encode(['success' => false, 'message' => 'Deposit hours closed. Allowed time is 10:00 AM to 10:00 PM.']);
        exit;
    }


    $channel = trim($data['channel'] ?? '');
    $amount = filter_var($data['amount'] ?? 0, FILTER_VALIDATE_FLOAT);
    $utr = trim($data['utr'] ?? '');
    $userEmail = $_SESSION['user_email'];

    if (!$amount || $amount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Please enter a valid deposit amount.']);
        exit;
    }

    if (empty($utr) || strlen($utr) < 6) {
        echo json_encode(['success' => false, 'message' => 'Please enter a valid UTR / Reference number.']);
        exit;
    }

    if (empty($channel)) {
        echo json_encode(['success' => false, 'message' => 'Please select a deposit channel.']);
        exit;
    }

    try {
         // Rule: Check if user has any approved/successful deposit in the last 30 minutes
        $recentDepositCheck = $pdo->prepare("SELECT id FROM deposit_history WHERE email = ? AND LOWER(status) IN ('approved', 'success', 'completed') AND created_at >= (NOW() - INTERVAL 30 MINUTE)");
        $recentDepositCheck->execute([$userEmail]);
        if ($recentDepositCheck->rowCount() > 0) {
            echo json_encode(['success' => false, 'message' => 'Please wait at least 30 minutes between your deposits.']);
            exit;
        }


        // Rule 1: Check if the user already has a pending deposit request
        $pendingCheck = $pdo->prepare("SELECT id FROM deposit_history WHERE email = ? AND LOWER(status) = 'pending'");
        $pendingCheck->execute([$userEmail]);
        if ($pendingCheck->rowCount() > 0) {
            echo json_encode(['success' => false, 'message' => 'Already have a pending deposit request. Please wait for it to be processed before submitting a new one.']);
            exit;
        }

        // Rule 2 & 3: Check history of this specific UTR
        $utrCheck = $pdo->prepare("SELECT status FROM deposit_history WHERE utr = ? ORDER BY id DESC LIMIT 1");
        $utrCheck->execute([$utr]);
        $existingUtr = $utrCheck->fetch(PDO::FETCH_ASSOC);

        if ($existingUtr) {
            $existingStatus = strtolower($existingUtr['status']);
            
            // If it's already approved, block it completely
            if ($existingStatus === 'approved' || $existingStatus === 'success' || $existingStatus === 'completed') {
                echo json_encode(['success' => false, 'message' => 'This UTR / Reference number has already been approved.']);
                exit;
            }
            
            // If it's pending globally from someone else, block it
            if ($existingStatus === 'pending') {
                echo json_encode(['success' => false, 'message' => 'UTR verification pending.']);
                exit;
            }
        }

        // Insert deposit entry into database
        $stmt = $pdo->prepare("INSERT INTO deposit_history (email, channel, amount, utr, status, created_at) VALUES (?, ?, ?, ?, 'Pending', NOW())");
        $stmt->execute([$userEmail, $channel, $amount, $utr]);

        // Send confirmation email to User safely inside try-catch
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com'; 
            $mail->SMTPAuth = true;
            $mail->Username = 'fbayinfyclick@gmail.com'; 
            $mail->Password = 'qaay meew eiin itvu'; 
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
            
            $mail->setFrom('fbayinfyclick@gmail.com', 'Fbay Official');
            $mail->addAddress($userEmail);
            $mail->isHTML(true);
            $mail->Subject = 'Deposit Request Initiated - Fbay';
            $mail->Body = '
            <!DOCTYPE html>
            <html>
            <head><meta charset="UTF-8"><title>Deposit Request</title></head>
            <body style="margin: 0; padding: 0; background-color: #0f172a; font-family: \'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif;">
                <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px; background-color: #1e293b; margin: 40px auto; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 25px rgba(0,0,0,0.3);">
                    <tr>
                        <td align="center" style="background: linear-gradient(135deg, #e11d48 0%, #be123c 100%); padding: 35px 20px;">
                            <h1 style="color: #ffffff; margin: 0; font-size: 24px; font-weight: 700;">Deposit Initiated</h1>
                            <p style="color: #fecdd3; margin: 8px 0 0 0; font-size: 14px;">Awaiting verification</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 35px 30px; color: #cbd5e1;">
                            <p style="font-size: 16px; color: #f8fafc;">Hello,</p>
                            <p style="font-size: 14px; color: #94a3b8; line-height: 1.6;">Your deposit request has been successfully submitted and is currently pending verification by our finance team.</p>
                            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #0f172a; border: 1px solid #334155; border-radius: 12px; margin-top: 15px;">
                                <tr>
                                    <td style="padding: 20px;">
                                        <table border="0" cellpadding="6" cellspacing="0" width="100%">
                                            <tr><td width="40%" style="color: #64748b; font-size: 13px;">Channel:</td><td width="60%" style="color: #f8fafc; font-size: 14px;">' . htmlspecialchars($channel) . '</td></tr>
                                            <tr><td style="color: #64748b; font-size: 13px;">Amount:</td><td style="color: #34d399; font-size: 15px; font-weight: 700;">Rs. ' . number_format($amount, 2) . '</td></tr>
                                            <tr><td style="color: #64748b; font-size: 13px;">UTR / Ref:</td><td style="color: #38bdf8; font-size: 14px; font-weight: 600;">' . htmlspecialchars($utr) . '</td></tr>
                                            <tr><td style="color: #64748b; font-size: 13px;">Status:</td><td style="color: #fbbf24; font-size: 14px; font-weight: 600;">Pending Verification</td></tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="background-color: #0b0f19; padding: 20px; border-top: 1px solid #334155;">
                            <p style="margin: 0; font-size: 12px; color: #64748b;">&copy; 2016-26 Fbay. All rights reserved.</p>
                        </td>
                    </tr>
                </table>
            </body>
            </html>';
            $mail->send();
        } catch (Exception $e) {}

        // Send notification email to Admin safely inside try-catch
        try {
            $adminEmail = "fbayinfyclick@gmail.com"; 
            $adminMail = new PHPMailer(true);
            $adminMail->isSMTP();
            $adminMail->Host = 'smtp.gmail.com'; 
            $adminMail->SMTPAuth = true;
            $adminMail->Username = 'fbayinfyclick@gmail.com'; 
            $adminMail->Password = 'qaay meew eiin itvu'; 
            $adminMail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $adminMail->Port = 587;
            
            $adminMail->setFrom('fbayinfyclick@gmail.com', 'Fbay System');
            $adminMail->addAddress($adminEmail);
            $adminMail->isHTML(true);
            $adminMail->Subject = 'New Deposit Request Pending Approval - Fbay';
            $adminMail->Body = '
            <!DOCTYPE html>
            <html>
            <head><meta charset="UTF-8"><title>Admin Deposit Alert</title></head>
            <body style="margin: 0; padding: 0; background-color: #0f172a; font-family: \'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif;">
                <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px; background-color: #1e293b; margin: 40px auto; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 25px rgba(0,0,0,0.3);">
                    <tr>
                        <td align="center" style="background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); padding: 35px 20px;">
                            <h1 style="color: #ffffff; margin: 0; font-size: 24px; font-weight: 700;">New Deposit Alert!</h1>
                            <p style="color: #93c5fd; margin: 8px 0 0 0; font-size: 14px;">Action required</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 35px 30px; color: #cbd5e1;">
                            <p style="font-size: 16px; color: #f8fafc;">Hello Admin,</p>
                            <p style="font-size: 14px; color: #94a3b8; line-height: 1.6;">A user has submitted a new deposit request that requires review.</p>
                            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #0f172a; border: 1px solid #334155; border-radius: 12px; margin-top: 15px; margin-bottom: 25px;">
                                <tr>
                                    <td style="padding: 20px;">
                                        <table border="0" cellpadding="6" cellspacing="0" width="100%">
                                            <tr><td width="40%" style="color: #64748b; font-size: 13px;">User Email:</td><td width="60%" style="color: #f8fafc; font-size: 14px;">' . htmlspecialchars($userEmail) . '</td></tr>
                                            <tr><td style="color: #64748b; font-size: 13px;">Channel:</td><td style="color: #f8fafc; font-size: 14px;">' . htmlspecialchars($channel) . '</td></tr>
                                            <tr><td style="color: #64748b; font-size: 13px;">Amount:</td><td style="color: #34d399; font-size: 15px; font-weight: 700;">Rs. ' . number_format($amount, 2) . '</td></tr>
                                            <tr><td style="color: #64748b; font-size: 13px;">UTR Reference:</td><td style="color: #38bdf8; font-size: 14px; font-weight: 600;">' . htmlspecialchars($utr) . '</td></tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                            <div align="center">
                                <a href="https://fbay.42web.io/iris/login" target="_blank" style="background-color: #2563eb; color: #ffffff; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: bold; font-size: 14px; display: inline-block;">Login to Admin Panel</a>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="background-color: #0b0f19; padding: 20px; border-top: 1px solid #334155;">
                            <p style="margin: 0; font-size: 12px; color: #64748b;">&copy; 2016-26 Fbay. All rights reserved.</p>
                        </td>
                    </tr>
                </table>
            </body>
            </html>';
            $adminMail->send();
        } catch (Exception $e) {}

        echo json_encode(['success' => true, 'message' => 'Deposit successfully.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error.']);
    }
    exit;
}

// --- 10. GET FINANCIAL RECORDS ACTION ---
if ($action === 'get_financial_records') {
    if (!isset($_SESSION['user_email'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    try {
        $userEmail = $_SESSION['user_email'];
        
        // 1. Fetch Deposit History
        $depStmt = $pdo->prepare("SELECT amount, utr, status, created_at, 'deposit' as type FROM deposit_history WHERE email = ?");
        $depStmt->execute([$userEmail]);
        $deposits = $depStmt->fetchAll(PDO::FETCH_ASSOC);

        // 2. Fetch Withdrawal History
        $wdStmt = $pdo->prepare("SELECT amount, net_amount, utr, status, created_at, 'withdrawal' as type FROM withdrawal_history WHERE email = ?");
        $wdStmt->execute([$userEmail]);
        $withdrawals = $wdStmt->fetchAll(PDO::FETCH_ASSOC);

        // 3. Fetch Referral Income History (Direct Sponsor Commission) - Added source email
        $refStmt = $pdo->prepare("SELECT reward as amount, email as source_email, 'Approved' as status, created_at, 'referral' as type FROM referral_income WHERE sponsor_email = ?");
        $refStmt->execute([$userEmail]);
        $referrals = $refStmt->fetchAll(PDO::FETCH_ASSOC);

        // 4. Fetch Level Income History (Indirect Upline Levels Commission) - Added source email and level
        $lvlStmt = $pdo->prepare("SELECT reward as amount, email as source_email, level, 'Approved' as status, created_at, 'level' as type FROM level_income WHERE upline_email = ?");
        $lvlStmt->execute([$userEmail]);
        $levels = $lvlStmt->fetchAll(PDO::FETCH_ASSOC);

        // 5. Fetch Orders / Task Commissions History
        $ordStmt = $pdo->prepare("SELECT commission as amount, status, created_at, 'order' as type FROM orders WHERE email = ?");
        $ordStmt->execute([$userEmail]);
        $orders = $ordStmt->fetchAll(PDO::FETCH_ASSOC);

        // 6. Fetch Transfer History (Sent & Received as 'transfer_sent' / 'transfer_received')
        $trfStmt = $pdo->prepare("
            SELECT amount, fee, net_amount, sender_email, recipient_email, status, created_at, 
            CASE WHEN sender_email = ? THEN 'transfer_sent' ELSE 'transfer_received' END as type 
            FROM transfer_history 
            WHERE sender_email = ? OR recipient_email = ?
        ");
        $trfStmt->execute([$userEmail, $userEmail, $userEmail]);
        $transfers = $trfStmt->fetchAll(PDO::FETCH_ASSOC);

        // 7. Merge all records together
        $records = array_merge($deposits, $withdrawals, $referrals, $levels, $orders, $transfers);

        // 8. Sort combined records by created_at descending (latest first)
        usort($records, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });

        echo json_encode(['success' => true, 'records' => $records]);
    } catch (\PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    exit;
}

// --- 11. SUBMIT WITHDRAWAL ACTION ---
if ($action === 'submit_withdrawal') {
    if (!isset($_SESSION['user_email'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    // Set timezone to Asia/Kolkata
    date_default_timezone_set('Asia/Kolkata');
    
    $currentDay = date('l'); // Monday, Tuesday, etc.
    $currentHour = (int)date('H');
    $currentMinute = (int)date('i');
    $currentTimeDecimal = $currentHour + ($currentMinute / 60);

    // Check Days: Monday to Saturday (exclude Sunday)
    if ($currentDay === 'Sunday') {
        echo json_encode(['success' => false, 'message' => 'Banking hour closed. Withdrawals are allowed Monday to Saturday.']);
        exit;
    }

    // Check Time: 3:00 PM (15:00) to 5:00 PM (17:00)
    if ($currentTimeDecimal < 15.0 || $currentTimeDecimal >= 21.0) {
        echo json_encode(['success' => false, 'message' => 'Banking hour closed. Allowed time is 3:00 PM to 5:00 PM.']);
        exit;
    }

    $userEmail = $_SESSION['user_email'];
    $amount = filter_var($data['amount'] ?? 0, FILTER_VALIDATE_FLOAT);

    if (!$amount || $amount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid withdrawal amount.']);
        exit;
    }

    // --- NEW CHECK: Prevent withdrawal if deposited within the last 24 hours ---
    $depositCheckStmt = $pdo->prepare("
        SELECT id FROM deposit_history 
        WHERE email = ? 
        AND (LOWER(status) = 'approved' OR LOWER(status) = 'success' OR LOWER(status) = 'completed')
        AND created_at >= (NOW() - INTERVAL 24 HOUR)
        LIMIT 1
    ");
    $depositCheckStmt->execute([$userEmail]);
    if ($depositCheckStmt->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'Withdrawal not allowed. You have made a deposit within the last 24 hours.']);
        exit;
    }
    
        // --- NEW CHECK: Prevent withdrawal if a transfer was made or received within the last 24 hours ---
    $transferCheckStmt = $pdo->prepare("
        SELECT id FROM transfer_history 
        WHERE (sender_email = ? OR recipient_email = ?) 
        AND (LOWER(status) = 'approved' OR LOWER(status) = 'success' OR LOWER(status) = 'completed')
        AND created_at >= (NOW() - INTERVAL 24 HOUR)
        LIMIT 1
    ");
    $transferCheckStmt->execute([$userEmail, $userEmail]);
    if ($transferCheckStmt->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'Withdrawal not allowed. You have made or received a transfer within the last 24 hours.']);
        exit;
    }

    // --------------------------------------------------------------------------

    // Check if user has bound a bank account and PAN
$bankStmt = $pdo->prepare("SELECT holder_name, bank_name, account_number, ifsc_code, pan FROM user_banks WHERE email = ?");
$bankStmt->execute([$userEmail]);
$bankData = $bankStmt->fetch(PDO::FETCH_ASSOC);

// Check if bank account is bound
if (!$bankData || empty($bankData['account_number'])) {
    echo json_encode(['success' => false, 'message' => 'Bind your bank card.']);
    exit;
}

// Separate check for PAN
if (empty($bankData['pan'])) {
    echo json_encode(['success' => false, 'message' => 'Please update your PAN for compliance.']);
    exit;
}


    // Check if user has sufficient balance
    $userStmt = $pdo->prepare("SELECT balance FROM users WHERE email = ?");
    $userStmt->execute([$userEmail]);
    $userData = $userStmt->fetch(PDO::FETCH_ASSOC);

    if (!$userData || $userData['balance'] < $amount) {
        echo json_encode(['success' => false, 'message' => 'Insufficient balance!']);
        exit;
    }

    // Check if there is already a pending withdrawal
    $pendingCheck = $pdo->prepare("SELECT id FROM withdrawal_history WHERE email = ? AND LOWER(status) = 'pending'");
    $pendingCheck->execute([$userEmail]);
    if ($pendingCheck->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'Last request pending .']);
        exit;
    }

    $fee = $amount * 0.20;
    $netReceive = $amount - $fee;
    $bankJsonString = json_encode($bankData);

    try {
        // Start Transaction for safe deduction & history entry
        $pdo->beginTransaction();

        // 1. Insert into withdrawal history
        $stmt = $pdo->prepare("INSERT INTO withdrawal_history (email, amount, net_amount, bank_details, utr, remark, status, created_at) VALUES (?, ?, ?, ?, '', '', 'Pending', NOW())");
        $stmt->execute([$userEmail, $amount, $netReceive, $bankJsonString]);

        // 2. Deduct amount from user's balance
        $updateBalance = $pdo->prepare("UPDATE users SET balance = balance - ? WHERE email = ?");
        $updateBalance->execute([$amount, $userEmail]);

        $pdo->commit();

        // Send confirmation email to User
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com'; 
            $mail->SMTPAuth = true;
            $mail->Username = 'fbayinfyclick@gmail.com'; 
            $mail->Password = 'qaay meew eiin itvu'; 
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
            
            $mail->setFrom('fbayinfyclick@gmail.com', 'Fbay Official');
            $mail->addAddress($userEmail);
            $mail->isHTML(true);
            $mail->Subject = 'Withdrawal Request Initiated - Fbay';
            $mail->Body = '
            <!DOCTYPE html>
            <html>
            <head><meta charset="UTF-8"><title>Withdrawal Request</title></head>
            <body style="margin: 0; padding: 0; background-color: #0f172a; font-family: sans-serif;">
                <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px; background-color: #1e293b; margin: 40px auto; border-radius: 16px; overflow: hidden;">
                    <tr>
                        <td align="center" style="background: linear-gradient(135deg, #e11d48 0%, #be123c 100%); padding: 35px 20px;">
                            <h1 style="color: #ffffff; margin: 0; font-size: 24px;">Withdrawal Initiated</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 35px 30px; color: #cbd5e1;">
                            <p>Your withdrawal request of <strong>Rs. ' . number_format($amount, 2) . '</strong> (After 20% fee deduction, net amount: <strong>Rs. ' . number_format($netReceive, 2) . '</strong>) has been successfully submitted.</p>
                            <p>Settlement takes between 24 to 72 hours.</p>
                        </td>
                    </tr>
                </table>
            </body>
            </html>';
            $mail->send();
        } catch (Exception $e) {}

        // Send notification email to Admin
        try {
            $adminEmail = "fbayinfyclick@gmail.com"; 
            $adminMail = new PHPMailer(true);
            $adminMail->isSMTP();
            $adminMail->Host = 'smtp.gmail.com'; 
            $adminMail->SMTPAuth = true;
            $adminMail->Username = 'fbayinfyclick@gmail.com'; 
            $adminMail->Password = 'qaay meew eiin itvu'; 
            $adminMail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $adminMail->Port = 587;
            
            $adminMail->setFrom('fbayinfyclick@gmail.com', 'Fbay System');
            $adminMail->addAddress($adminEmail);
            $adminMail->isHTML(true);
            $adminMail->Subject = 'New Withdrawal Request Pending Approval - Fbay';
            $adminMail->Body = '
            <!DOCTYPE html>
            <html>
            <head><meta charset="UTF-8"><title>Admin Withdrawal Alert</title></head>
            <body style="margin: 0; padding: 0; background-color: #0f172a; font-family: \'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif;">
                <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px; background-color: #1e293b; margin: 40px auto; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 25px rgba(0,0,0,0.3);">
                    <tr>
                        <td align="center" style="background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); padding: 35px 20px;">
                            <h1 style="color: #ffffff; margin: 0; font-size: 24px; font-weight: 700;">New Withdrawal Alert!</h1>
                            <p style="color: #93c5fd; margin: 8px 0 0 0; font-size: 14px;">Action required</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 35px 30px; color: #cbd5e1;">
                            <p style="font-size: 16px; color: #f8fafc;">Hello Admin,</p>
                            <p style="font-size: 14px; color: #94a3b8; line-height: 1.6;">A user has submitted a new withdrawal request that requires review.</p>
                            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #0f172a; border: 1px solid #334155; border-radius: 12px; margin-top: 15px; margin-bottom: 25px;">
                                <tr>
                                    <td style="padding: 20px;">
                                        <table border="0" cellpadding="6" cellspacing="0" width="100%">
                                            <tr><td width="40%" style="color: #64748b; font-size: 13px;">User Email:</td><td width="60%" style="color: #f8fafc; font-size: 14px;">' . htmlspecialchars($userEmail) . '</td></tr>
                                            <tr><td style="color: #64748b; font-size: 13px;">Requested Amount:</td><td style="color: #34d399; font-size: 15px; font-weight: 700;">Rs. ' . number_format($amount, 2) . '</td></tr>
                                            <tr><td style="color: #64748b; font-size: 13px;">Net After 20% Fee:</td><td style="color: #38bdf8; font-size: 14px; font-weight: 600;">Rs. ' . number_format($netReceive, 2) . '</td></tr>
                                            <tr><td style="color: #64748b; font-size: 13px;">Bank Account:</td><td style="color: #f8fafc; font-size: 14px;">' . htmlspecialchars($bankData['account_number']) . ' (' . htmlspecialchars($bankData['bank_name']) . ')</td></tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                            <div align="center">
                                <a href="https://fbay.42web.io/iris/login" target="_blank" style="background-color: #2563eb; color: #ffffff; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: bold; font-size: 14px; display: inline-block;">Login to Admin Panel</a>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="background-color: #0b0f19; padding: 20px; border-top: 1px solid #334155;">
                            <p style="margin: 0; font-size: 12px; color: #64748b;">&copy; 2016-26 Fbay. All rights reserved.</p>
                        </td>
                    </tr>
                </table>
            </body>
            </html>';
            $adminMail->send();
        } catch (Exception $e) {}

        echo json_encode(['success' => true, 'message' => 'Withdrawal successfully!']);
    } catch (\PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'message' => 'Database error.']);
    }
    exit;
}
// --- 12. BUY INVESTMENT PLAN ACTION ---
if ($action === 'buy_plan') {
    if (!isset($_SESSION['user_email'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    $userEmail = $_SESSION['user_email'];
    $planName = trim($data['plan_name'] ?? '');
    $amount = filter_var($data['amount'] ?? 0, FILTER_VALIDATE_FLOAT);
    $dailyReward = filter_var($data['daily_reward'] ?? 0, FILTER_VALIDATE_FLOAT);

    if (empty($planName) || !$amount || $amount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid plan details.']);
        exit;
    }

    // 1. Check if user already holds this exact active plan
    $activePlanCheck = $pdo->prepare("SELECT id FROM user_plans WHERE email = ? AND plan_name = ? AND LOWER(status) = 'active'");
    $activePlanCheck->execute([$userEmail, $planName]);
    if ($activePlanCheck->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'Already have an active ' . $planName]);
        exit;
    }

    // 2. Check if user has sufficient balance
    $userStmt = $pdo->prepare("SELECT balance, sponsor FROM users WHERE email = ?");
    $userStmt->execute([$userEmail]);
    $userData = $userStmt->fetch(PDO::FETCH_ASSOC);

    if (!$userData || $userData['balance'] < $amount) {
        echo json_encode(['success' => false, 'message' => 'Insufficient balance!']);
        exit;
    }

    try {
        // Start Transaction
        $pdo->beginTransaction();

        // 3. Mark any existing active plans for this user as 'Completed' when upgrading/buying a new one
        $updateOldPlans = $pdo->prepare("UPDATE user_plans SET status = 'Completed' WHERE email = ? AND LOWER(status) = 'active'");
        $updateOldPlans->execute([$userEmail]);

        // 4. Deduct balance from users table
        $updateBalance = $pdo->prepare("UPDATE users SET balance = balance - ? WHERE email = ?");
        $updateBalance->execute([$amount, $userEmail]);

        // 5. Insert new plan into user_plans table
        $insertPlan = $pdo->prepare("INSERT INTO user_plans (email, plan_name, amount, daily_reward, last_claim_at, claimed_days, status, created_at) VALUES (?, ?, ?, ?, NULL, 0, 'Active', NOW())");
        $insertPlan->execute([$userEmail, $planName, $amount, $dailyReward]);

        // ========================================================
        // 6. MULTI-LEVEL COMMISSION LOGIC (Conditional on Active Plan)
        // ========================================================
        $currentEmail = $userEmail;
        
        // Helper function to check if a user has at least one active plan
        $hasActivePlan = function($email) use ($pdo) {
            $stmt = $pdo->prepare("SELECT id FROM user_plans WHERE email = ? AND LOWER(status) = 'active' LIMIT 1");
            $stmt->execute([$email]);
            return $stmt->rowCount() > 0;
        };

        // Level 1: Direct Sponsor (10%)
        $currentUserStmt = $pdo->prepare("SELECT sponsor FROM users WHERE email = ?");
        $currentUserStmt->execute([$currentEmail]);
        $currentUserData = $currentUserStmt->fetch(PDO::FETCH_ASSOC);

        if ($currentUserData && !empty($currentUserData['sponsor'])) {
            $sponsorCodeOrEmail = $currentUserData['sponsor'];
            
            // Find direct sponsor email & record
            $sponsorQuery = $pdo->prepare("SELECT email FROM users WHERE email = ? OR referral_code = ?");
            $sponsorQuery->execute([$sponsorCodeOrEmail, $sponsorCodeOrEmail]);
            $sponsorData = $sponsorQuery->fetch(PDO::FETCH_ASSOC);

            if ($sponsorData) {
                $directSponsorEmail = $sponsorData['email'];

                // Check if Direct Sponsor has an active plan before rewarding
                if ($hasActivePlan($directSponsorEmail)) {
                    $directCommission = $amount * 0.10; // 10%

                    // Add to direct sponsor balance
                    $updSp = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE email = ?");
                    $updSp->execute([$directCommission, $directSponsorEmail]);

                    // Insert into referral_income table
                    $insRef = $pdo->prepare("INSERT INTO referral_income (email, sponsor_email, amount, reward, created_at) VALUES (?, ?, ?, ?, NOW())");
                    $insRef->execute([$userEmail, $directSponsorEmail, $amount, $directCommission]);
                }

                // Level 2, 3, 4 (Indirect Levels: 3%, 2%, 0.5%)
                $percentages = [0.03, 0.02, 0.005];
                $nextTargetEmail = $directSponsorEmail;

                for ($lvl = 0; $lvl < count($percentages); $lvl++) {
                    $nextSpStmt = $pdo->prepare("SELECT sponsor FROM users WHERE email = ?");
                    $nextSpStmt->execute([$nextTargetEmail]);
                    $nextSpData = $nextSpStmt->fetch(PDO::FETCH_ASSOC);

                    if (!$nextSpData || empty($nextSpData['sponsor'])) {
                        break; // No further upline
                    }

                    $uplineCodeOrEmail = $nextSpData['sponsor'];
                    $uplineQuery = $pdo->prepare("SELECT email FROM users WHERE email = ? OR referral_code = ?");
                    $uplineQuery->execute([$uplineCodeOrEmail, $uplineCodeOrEmail]);
                    $uplineData = $uplineQuery->fetch(PDO::FETCH_ASSOC);

                    if (!$uplineData) {
                        break;
                    }

                    $uplineEmail = $uplineData['email'];

                    // Check if this Upline level has an active plan before rewarding
                    if ($hasActivePlan($uplineEmail)) {
                        $levelCommission = $amount * $percentages[$lvl];

                        // Add to upline balance
                        $updUpline = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE email = ?");
                        $updUpline->execute([$levelCommission, $uplineEmail]);

                        // Insert into level_income table
                        $insLevel = $pdo->prepare("INSERT INTO level_income (email, upline_email, level, amount, reward, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                        $insLevel->execute([$userEmail, $uplineEmail, ($lvl + 2), $amount, $levelCommission]);
                    }

                    $nextTargetEmail = $uplineEmail;
                }
            }
        }

        $pdo->commit();

        // Send success confirmation email to user via PHPMailer
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com'; 
            $mail->SMTPAuth = true;
            $mail->Username = 'fbayinfyclick@gmail.com'; 
            $mail->Password = 'qaay meew eiin itvu'; 
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
            
            $mail->setFrom('fbayinfyclick@gmail.com', 'Fbay Official');
            $mail->addAddress($userEmail);
            $mail->isHTML(true);
            $mail->Subject = 'Investment Plan Activated Successfully - Fbay';
            $mail->Body = '
            <!DOCTYPE html>
            <html>
            <head><meta charset="UTF-8"><title>Plan Activated</title></head>
            <body style="margin: 0; padding: 0; background-color: #0f172a; font-family: sans-serif;">
                <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px; background-color: #1e293b; margin: 40px auto; border-radius: 16px; overflow: hidden;">
                    <tr>
                        <td align="center" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); padding: 35px 20px;">
                            <h1 style="color: #ffffff; margin: 0; font-size: 24px;">Plan Activated Successfully</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 35px 30px; color: #cbd5e1;">
                            <p style="font-size: 16px; color: #f8fafc;">Hello,</p>
                            <p>Your new investment plan <strong>' . htmlspecialchars($planName) . '</strong> has been activated successfully! Any previous active plan has been marked as completed.</p>
                            <table border="0" cellpadding="6" cellspacing="0" width="100%" style="background-color: #0f172a; border-radius: 12px; margin: 20px 0;">
                                <tr><td style="color: #94a3b8;">Plan Name:</td><td style="color: #ffffff; font-weight: bold;">' . htmlspecialchars($planName) . '</td></tr>
                                <tr><td style="color: #94a3b8;">Invested Amount:</td><td style="color: #34d399; font-weight: bold;">Rs. ' . number_format($amount, 2) . '</td></tr>
                                <tr><td style="color: #94a3b8;">Daily Reward:</td><td style="color: #38bdf8; font-weight: bold;">Rs. ' . number_format($dailyReward, 2) . ' / day</td></tr>
                            </table>
                            <p>You can now start claiming your daily rewards from your dashboard.</p>
                        </td>
                    </tr>
                    <tr>
                    <td align="center" style="background-color: #0b0f19; padding: 20px; border-top: 1px solid #334155;">
                        <p style="margin: 0; font-size: 12px; color: #64748b;">&copy; 2016-26 Fbay. All rights reserved.</p>
                    </td>
                </tr>
                </table>
            </body>
            </html>';
            $mail->send();
        } catch (Exception $e) {}

        echo json_encode(['success' => true, 'message' => 'Plan purchased successfully! ']);
    } catch (\PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    exit;
}
// ============================================================
// 13. GET USER PLANS
// ============================================================
if ($action === 'get_user_plans') {

    if (!isset($_SESSION['user_email'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Unauthorized'
        ]);
        exit;
    }

    try {

        $userEmail = $_SESSION['user_email'];

        $stmt = $pdo->prepare("
            SELECT *
            FROM user_plans
            WHERE email = ?
            ORDER BY id DESC
        ");

        $stmt->execute([$userEmail]);

        $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

        date_default_timezone_set('Asia/Kolkata');

        $currentTime = new DateTime('now', new DateTimeZone('Asia/Kolkata'));

        foreach ($plans as &$plan) {

            // Default
            $plan['can_claim'] = false;

            // Sirf active plan claim kar sakta hai
            if (strtolower($plan['status']) === 'active') {

                $canClaim = true;

                if (!empty($plan['last_claim_at'])) {

                    $lastClaim = new DateTime(
                        $plan['last_claim_at'],
                        new DateTimeZone('Asia/Kolkata')
                    );

                    $diffSeconds =
                        $currentTime->getTimestamp()
                        - $lastClaim->getTimestamp();

                    // 24 hours complete nahi hue
                    if ($diffSeconds < 86400) {
                        $canClaim = false;
                    }
                }

                $plan['can_claim'] = $canClaim;
            }
        }

        unset($plan);

        echo json_encode([
            'success' => true,
            'plans' => $plans
        ]);

    } catch (PDOException $e) {

        echo json_encode([
            'success' => false,
            'message' => 'Database error'
        ]);
    }

    exit;
}


// ============================================================
// 14. SUBMIT ORDER (Fixed Daily Reward Logic)
// ============================================================
if ($action === 'submit_order') {

    if (!isset($_SESSION['user_email'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Unauthorized user'
        ]);
        exit;
    }

    $userEmail = $_SESSION['user_email'];

    $productName  = $data['product_name'] ?? '';
    $productPrice = filter_var(
        $data['product_price'] ?? 0,
        FILTER_VALIDATE_FLOAT
    );
    $commission = filter_var(
        $data['commission'] ?? 0,
        FILTER_VALIDATE_FLOAT
    );

    if (
        empty($productName) ||
        $productPrice === false ||
        $commission === false ||
        $commission <= 0
    ) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid order data'
        ]);
        exit;
    }

    try {

        $pdo->beginTransaction();

        // 1. Active plan fetch karo
        $stmtPlan = $pdo->prepare("
            SELECT id, daily_reward 
            FROM user_plans
            WHERE email = ? AND status = 'active'
            ORDER BY id DESC
            LIMIT 1
            FOR UPDATE
        ");
        $stmtPlan->execute([$userEmail]);
        $plan = $stmtPlan->fetch(PDO::FETCH_ASSOC);

        if (!$plan) {
            $pdo->rollBack();
            echo json_encode([
                'success' => true, // Error nahi, success bhejenge taaki UI na ruke
                'message' => 'Plan processed'
            ]);
            exit;
        }

        $dailyRewardLimit = (float)$plan['daily_reward'];

        // 2. Aaj ka total earned check karo
        $stmtSum = $pdo->prepare("
            SELECT SUM(commission) as total_earned_today 
            FROM orders 
            WHERE email = ? 
            AND DATE(created_at) = CURDATE()
        ");
        $stmtSum->execute([$userEmail]);
        $sumResult = $stmtSum->fetch(PDO::FETCH_ASSOC);
        $alreadyEarnedToday = (float)($sumResult['total_earned_today'] ?? 0);

        // Agar limit poori ho chuki hai, toh error mat do, bas success return kar do taaki aage ka flow na ruke
        if ($alreadyEarnedToday >= $dailyRewardLimit) {
            $pdo->commit();
            echo json_encode([
                'success' => true,
                'message' => 'Order already processed!'
            ]);
            exit;
        }

        // 3. Commission adjustment taaki limit se zyada na ho
        $actualCommissionToAdd = $commission;
        if (($alreadyEarnedToday + $commission) > $dailyRewardLimit) {
            $actualCommissionToAdd = $dailyRewardLimit - $alreadyEarnedToday;
        }

        if ($actualCommissionToAdd > 0) {
            $stmtOrder = $pdo->prepare("
                INSERT INTO orders
                (email, product_name, product_price, commission, status, created_at)
                VALUES (?, ?, ?, ?, 'Completed', NOW())
            ");
            $stmtOrder->execute([$userEmail, $productName, $productPrice, $actualCommissionToAdd]);

            $stmtUser = $pdo->prepare("
                UPDATE users
                SET balance = balance + ?
                WHERE email = ?
            ");
            $stmtUser->execute([$actualCommissionToAdd, $userEmail]);
        }

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Order submitted successfully!'
        ]);

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode([
            'success' => false,
            'message' => 'Database error'
        ]);
    }
    exit;
}

// ============================================================
// 15. COMPLETE DAILY TASKS
// Last order ke baad ye call hoga
// ============================================================
if ($action === 'complete_daily_tasks') {

    if (!isset($_SESSION['user_email'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Unauthorized user'
        ]);
        exit;
    }

    $userEmail = $_SESSION['user_email'];

    try {

        date_default_timezone_set('Asia/Kolkata');

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            SELECT id, daily_reward, claimed_days, last_claim_at
            FROM user_plans
            WHERE email = ?
            AND status = 'active'
            ORDER BY id DESC
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute([$userEmail]);
        $plan = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$plan) {
            $pdo->rollBack();
            echo json_encode([
                'success' => true,
                'message' => 'No active plan'
            ]);
            exit;
        }

        // Agar pichle 24 ghante mein pehle hi claim ho chuka hai, toh error mat do, success return kar do
        if (!empty($plan['last_claim_at'])) {
            $currentTime = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
            $lastClaim = new DateTime($plan['last_claim_at'], new DateTimeZone('Asia/Kolkata'));
            $diffSeconds = $currentTime->getTimestamp() - $lastClaim->getTimestamp();

            if ($diffSeconds < 86400) {
                $pdo->commit(); // Commit karke success bhej do taaki double click par error popup na aaye
                echo json_encode([
                    'success' => true,
                    'message' => 'Already claimed for today!'
                ]);
                exit;
            }
        }

        // Claim update
        $stmtUpdate = $pdo->prepare("
            UPDATE user_plans
            SET
                claimed_days = COALESCE(claimed_days, 0) + 1,
                last_claim_at = NOW()
            WHERE id = ?
        ");
        $stmtUpdate->execute([$plan['id']]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Daily tasks completed successfully!'
        ]);

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode([
            'success' => false,
            'message' => 'Database error'
        ]);
    }
    exit;
}
echo json_encode(['success' => false, 'message' => 'Invalid action.']);

// --- 16 & 17. TRANSFER ACTIONS HANDLER ---
if ($action === 'send_transfer_otp') {
    if (!isset($_SESSION['user_email'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    date_default_timezone_set('Asia/Kolkata');
    $senderEmail = $_SESSION['user_email'];
    $recipientEmail = trim($data['email'] ?? '');
    $amount = filter_var($data['amount'] ?? 0, FILTER_VALIDATE_FLOAT);

    if (!$amount || $amount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid amount']);
        exit;
    }

    if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email']);
        exit;
    }

    if ($senderEmail === $recipientEmail) {
        echo json_encode(['success' => false, 'message' => 'Cannot transfer to yourself']);
        exit;
    }

    // Check if recipient exists
    $recipientCheck = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $recipientCheck->execute([$recipientEmail]);
    if ($recipientCheck->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Recipient not found']);
        exit;
    }

    // Check 24-hour deposit restriction
    $depositCheckStmt = $pdo->prepare("
        SELECT id FROM deposit_history 
        WHERE email = ? 
        AND (LOWER(status) = 'approved' OR LOWER(status) = 'success' OR LOWER(status) = 'completed')
        AND created_at >= (NOW() - INTERVAL 24 HOUR)
        LIMIT 1
    ");
    $depositCheckStmt->execute([$senderEmail]);
    if ($depositCheckStmt->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'Deposit made within last 24h']);
        exit;
    }

    // Check once-a-month limit per recipient
    $monthlyCheck = $pdo->prepare("
        SELECT id FROM transfer_history 
        WHERE sender_email = ? AND recipient_email = ? 
        AND MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())
        LIMIT 1
    ");
    $monthlyCheck->execute([$senderEmail, $recipientEmail]);
    if ($monthlyCheck->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'Already transferred this month']);
        exit;
    }

    // Check sufficient balance
    $userStmt = $pdo->prepare("SELECT balance FROM users WHERE email = ?");
    $userStmt->execute([$senderEmail]);
    $userData = $userStmt->fetch(PDO::FETCH_ASSOC);

    if (!$userData || $userData['balance'] < $amount) {
        echo json_encode(['success' => false, 'message' => 'Insufficient balance']);
        exit;
    }

    // Generate and Save OTP
    $otp = rand(100000, 999999);
    $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    $stmt = $pdo->prepare("DELETE FROM otps WHERE email = ?");
    $stmt->execute([$senderEmail]);

    $stmt = $pdo->prepare("INSERT INTO otps (email, otp, expires_at) VALUES (?, ?, ?)");
    $stmt->execute([$senderEmail, $otp, $expires_at]);

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com'; 
        $mail->SMTPAuth = true;
        $mail->Username = 'fbayinfyclick@gmail.com'; 
        $mail->Password = 'qaay meew eiin itvu'; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        $mail->setFrom('fbayinfyclick@gmail.com', 'Fbay Official');
        $mail->addAddress($senderEmail);
        $mail->isHTML(true);
        $mail->Subject = 'Transfer Verification Code - Fbay';
        $mail->Body = '
        <!DOCTYPE html>
        <html>
        <head><meta charset="UTF-8"><title>Transfer OTP</title></head>
        <body style="margin: 0; padding: 0; background-color: #0f172a; font-family: sans-serif;">
            <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px; background-color: #1e293b; margin: 40px auto; border-radius: 16px; overflow: hidden;">
                <tr>
                    <td align="center" style="background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); padding: 35px 20px;">
                        <h1 style="color: #ffffff; margin: 0; font-size: 24px;">Transfer Verification</h1>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 35px 30px; color: #cbd5e1;">
                        <p>Your OTP code for transferring funds to <strong>' . htmlspecialchars($recipientEmail) . '</strong> is:</p>
                        <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #0f172a; border: 1px solid #334155; border-radius: 12px; margin: 20px 0;">
                            <tr>
                                <td align="center" style="padding: 20px;">
                                    <span style="font-size: 32px; font-weight: 800; letter-spacing: 8px; color: #38bdf8;">' . $otp . '</span>
                                </td>
                            </tr>
                        </table>
                        <p style="font-size: 13px; color: #64748b; text-align: center;">Expires in 10 minutes.</p>
                    </td>
                </tr>
                <tr>
                    <td align="center" style="background-color: #0b0f19; padding: 20px; border-top: 1px solid #334155;">
                        <p style="margin: 0; font-size: 12px; color: #64748b;">&copy; 2016-26 Fbay. All rights reserved.</p>
                    </td>
                </tr>
            </table>
        </body>
        </html>';
        
        $mail->send();
        echo json_encode(['success' => true, 'message' => 'OTP sent successfully']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to send OTP email']);
    }
    exit;
} 
else if ($action === 'submit_transfer') {
    if (!isset($_SESSION['user_email'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    $senderEmail = $_SESSION['user_email'];
    $recipientEmail = trim($data['email'] ?? '');
    $amount = filter_var($data['amount'] ?? 0, FILTER_VALIDATE_FLOAT);
    $otpInput = trim($data['otp'] ?? '');

    if (!$amount || $amount <= 0 || empty($recipientEmail) || empty($otpInput)) {
        echo json_encode(['success' => false, 'message' => 'Invalid input data']);
        exit;
    }

    // Verify OTP
    $otpStmt = $pdo->prepare("SELECT * FROM otps WHERE email = ? AND otp = ? AND expires_at >= NOW()");
    $otpStmt->execute([$senderEmail, $otpInput]);
    if ($otpStmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired OTP']);
        exit;
    }

    $fee = $amount * 0.10;
    $netReceive = $amount - $fee;

    try {
        $pdo->beginTransaction();

        // 1. Insert into transfer history
        $stmt = $pdo->prepare("INSERT INTO transfer_history (sender_email, recipient_email, amount, fee, net_amount, status, created_at) VALUES (?, ?, ?, ?, ?, 'Success', NOW())");
        $stmt->execute([$senderEmail, $recipientEmail, $amount, $fee, $netReceive]);

        // 2. Deduct full amount from sender's balance
        $updateSender = $pdo->prepare("UPDATE users SET balance = balance - ? WHERE email = ?");
        $updateSender->execute([$amount, $senderEmail]);

        // 3. Add net amount to recipient's balance
        $updateRecipient = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE email = ?");
        $updateRecipient->execute([$netReceive, $recipientEmail]);

        // 4. Delete used OTP
        $deleteOtp = $pdo->prepare("DELETE FROM otps WHERE email = ?");
        $deleteOtp->execute([$senderEmail]);

        $pdo->commit();

        // Send confirmation email to Sender
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com'; 
            $mail->SMTPAuth = true;
            $mail->Username = 'fbayinfyclick@gmail.com'; 
            $mail->Password = 'qaay meew eiin itvu'; 
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
            
            $mail->setFrom('fbayinfyclick@gmail.com', 'Fbay Official');
            $mail->addAddress($senderEmail);
            $mail->isHTML(true);
            $mail->Subject = 'Fund Transfer Successful - Fbay';
            $mail->Body = '
            <!DOCTYPE html>
            <html>
            <head><meta charset="UTF-8"><title>Transfer Confirmation</title></head>
            <body style="margin: 0; padding: 0; background-color: #0f172a; font-family: sans-serif;">
                <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px; background-color: #1e293b; margin: 40px auto; border-radius: 16px; overflow: hidden;">
                    <tr>
                        <td align="center" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); padding: 35px 20px;">
                            <h1 style="color: #ffffff; margin: 0; font-size: 24px;">Transfer Successful</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 35px 30px; color: #cbd5e1;">
                            <p>Transferred <strong>Rs. ' . number_format($amount, 2) . '</strong> to <strong>' . htmlspecialchars($recipientEmail) . '</strong>.</p>
                            <p>Fee (10%): Rs. ' . number_format($fee, 2) . '</p>
                            <p>Net Amount Sent: <strong>Rs. ' . number_format($netReceive, 2) . '</strong></p>
                        </td>
                    </tr>
                    <tr>
                    <td align="center" style="background-color: #0b0f19; padding: 20px; border-top: 1px solid #334155;">
                        <p style="margin: 0; font-size: 12px; color: #64748b;">&copy; 2016-26 Fbay. All rights reserved.</p>
                    </td>
                </tr>
                </table>
            </body>
            </html>';
            $mail->send();
        } catch (Exception $e) {}

        // Send notification email to Recipient
        try {
            $recipientMail = new PHPMailer(true);
            $recipientMail->isSMTP();
            $recipientMail->Host = 'smtp.gmail.com'; 
            $recipientMail->SMTPAuth = true;
            $recipientMail->Username = 'fbayinfyclick@gmail.com'; 
            $recipientMail->Password = 'qaay meew eiin itvu'; 
            $recipientMail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $recipientMail->Port = 587;
            
            $recipientMail->setFrom('fbayinfyclick@gmail.com', 'Fbay Official');
            $recipientMail->addAddress($recipientEmail);
            $recipientMail->isHTML(true);
            $recipientMail->Subject = 'Funds Received - Fbay';
            $recipientMail->Body = '
            <!DOCTYPE html>
            <html>
            <head><meta charset="UTF-8"><title>Funds Received</title></head>
            <body style="margin: 0; padding: 0; background-color: #0f172a; font-family: sans-serif;">
                <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px; background-color: #1e293b; margin: 40px auto; border-radius: 16px; overflow: hidden;">
                    <tr>
                        <td align="center" style="background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); padding: 35px 20px;">
                            <h1 style="color: #ffffff; margin: 0; font-size: 24px;">Funds Received</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 35px 30px; color: #cbd5e1;">
                            <p>You received <strong>Rs. ' . number_format($netReceive, 2) . '</strong> from <strong>' . htmlspecialchars($senderEmail) . '</strong>.</p>
                            <p>Credited instantly to your balance.</p>
                        </td>
                    </tr>
                    <tr>
                    <td align="center" style="background-color: #0b0f19; padding: 20px; border-top: 1px solid #334155;">
                        <p style="margin: 0; font-size: 12px; color: #64748b;">&copy; 2016-26 Fbay. All rights reserved.</p>
                    </td>
                </tr>
                </table>
            </body>
            </html>';
            $recipientMail->send();
        } catch (Exception $e) {}

        echo json_encode(['success' => true, 'message' => 'Transfer completed successfully']);
    } catch (\PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    exit;
}
// 18 --- GET TEAM SUMMARY ACTION (Up to 4 Levels Breakdown) ---
if ($action === 'get_team_summary') {
    // Clear any previous output buffers to prevent garbage data
    if (ob_get_length()) ob_clean();
    
    header('Content-Type: application/json');

    if (!isset($_SESSION['user_email'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT referral_code FROM users WHERE email = ?");
    $stmt->execute([$_SESSION['user_email']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $summary = [];
    $maxLevels = 4;

    if ($user && !empty($user['referral_code'])) {
        $currentSponsors = [$user['referral_code']];

        for ($lvl = 1; $lvl <= $maxLevels; $lvl++) {
            if (empty($currentSponsors)) {
                for ($remaining = $lvl; $remaining <= $maxLevels; $remaining++) {
                    $summary[] = [
                        'level' => $remaining,
                        'total_members' => 0,
                        'active_members' => 0,
                        'inactive_members' => 0,
                        'total_plan_amount' => 0.00
                    ];
                }
                break;
            }

            $placeholders = implode(',', array_fill(0, count($currentSponsors), '?'));
            
            $stmt = $pdo->prepare("SELECT email, referral_code FROM users WHERE sponsor IN ($placeholders)");
            $stmt->execute($currentSponsors);
            $levelUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $totalMembers = count($levelUsers);
            $activeMembers = 0;
            $inactiveMembers = 0;
            $totalPlanAmount = 0;
            $nextSponsors = [];

            foreach ($levelUsers as $member) {
                if (!empty($member['referral_code'])) {
                    $nextSponsors[] = $member['referral_code'];
                }

                $planStmt = $pdo->prepare("SELECT amount, status FROM user_plans WHERE email = ?");
                $planStmt->execute([$member['email']]);
                $userPlans = $planStmt->fetchAll(PDO::FETCH_ASSOC);

                $hasActivePlan = false;
                $memberPlanTotal = 0;

                foreach ($userPlans as $plan) {
                    $memberPlanTotal += floatval($plan['amount']);
                    if (strcasecmp($plan['status'], 'Active') === 0) {
                        $hasActivePlan = true;
                    }
                }

                if ($hasActivePlan) {
                    $activeMembers++;
                    $totalPlanAmount += $memberPlanTotal;
                } else {
                    $inactiveMembers++;
                }
            }

            $summary[] = [
                'level' => $lvl,
                'total_members' => $totalMembers,
                'active_members' => $activeMembers,
                'inactive_members' => $inactiveMembers,
                'total_plan_amount' => $totalPlanAmount
            ];

            $currentSponsors = $nextSponsors;
        }
    } else {
        for ($lvl = 1; $lvl <= $maxLevels; $lvl++) {
            $summary[] = [
                'level' => $lvl,
                'total_members' => 0,
                'active_members' => 0,
                'inactive_members' => 0,
                'total_plan_amount' => 0.00
            ];
        }
    }

    echo json_encode(['success' => true, 'summary' => $summary]);
    exit;
}
// --- GET SALARY PROGRESS ACTION ---
if ($action === 'get_salary_progress') {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');

    if (!isset($_SESSION['user_email'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    $userEmail = $_SESSION['user_email'];

    // 1. Get User's Active Plan Name
    $planStmt = $pdo->prepare("SELECT plan_name FROM user_plans WHERE email = ? AND status = 'Active' LIMIT 1");
    $planStmt->execute([$userEmail]);
    $activePlan = $planStmt->fetch(PDO::FETCH_ASSOC);
    $currentPlanName = $activePlan ? trim($activePlan['plan_name']) : 'None';

    // 2. Get User's Referral Code for team tracking across 3 levels
    $userStmt = $pdo->prepare("SELECT referral_code FROM users WHERE email = ?");
    $userStmt->execute([$userEmail]);
    $userData = $userStmt->fetch(PDO::FETCH_ASSOC);

    $totalTeamMembers = 0;
    if ($userData && !empty($userData['referral_code'])) {
        $currentSponsors = [$userData['referral_code']];
        for ($lvl = 1; $lvl <= 3; $lvl++) {
            if (empty($currentSponsors)) break;
            $placeholders = implode(',', array_fill(0, count($currentSponsors), '?'));
            
            $teamStmt = $pdo->prepare("SELECT email, referral_code FROM users WHERE sponsor IN ($placeholders)");
            $teamStmt->execute($currentSponsors);
            $members = $teamStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($members as $member) {
                // Check if member has an active plan
                $mPlanStmt = $pdo->prepare("SELECT id FROM user_plans WHERE email = ? AND status = 'Active' LIMIT 1");
                $mPlanStmt->execute([$member['email']]);
                if ($mPlanStmt->fetch()) {
                    $totalTeamMembers++;
                }
            }
            $currentSponsors = array_column($members, 'referral_code');
        }
    }

    // Monthly Unlocks check (Requires User Plan match + 18 active members in 3 levels)
    $unlockedMonthly = [];
    $tiers = ['Silver', 'Gold', 'Platinum', 'Diamond', 'Elite Pro'];
    foreach ($tiers as $tier) {
        if (strcasecmp($currentPlanName, $tier) === 0 && $totalTeamMembers >= 18) {
            $unlockedMonthly[] = $tier;
        }
    }

    // 3. Weekly Conditions (Monday & Tuesday check)
    $mondayThisWeek = date('Y-m-d', strtotime('monday this week'));
    $tuesdayThisWeek = date('Y-m-d', strtotime('tuesday this week'));
    $monTueReferrals = 0;

    if ($userData && !empty($userData['referral_code'])) {
        $weeklyStmt = $pdo->prepare("
            SELECT COUNT(*) as ref_count 
            FROM users 
            WHERE sponsor = ? AND DATE(created_at) BETWEEN ? AND ?
        ");
        $weeklyStmt->execute([$userData['referral_code'], $mondayThisWeek, $tuesdayThisWeek]);
        $weeklyData = $weeklyStmt->fetch(PDO::FETCH_ASSOC);
        $monTueReferrals = $weeklyData ? (int)$weeklyData['ref_count'] : 0;
    }

    $weeklyEligible = ($monTueReferrals >= 2 && $currentPlanName !== 'None');

    echo json_encode([
        'success' => true,
        'current_plan' => $currentPlanName,
        'team_count' => $totalTeamMembers,
        'unlocked_monthly' => $unlockedMonthly,
        'weekly_eligible' => $weeklyEligible,
        'mon_tue_referrals' => $monTueReferrals
    ]);
    exit;
}

?>