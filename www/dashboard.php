<?php
session_start();

if (!isset($_SESSION['user_email'])) {
    header("Location: /fbay");
    exit;
}

$host = 'sql102.infinityfree.com';
$db   = 'if0_42376779_fbay';
$user = 'if0_42376779';
$pass = 'Fbay2026';

$fullName = "User";
$balance = "0.00";
$referralCode = "FBAY1234";
$sponsorName = "N/A";
$userEmail = $_SESSION['user_email'];

// Variables for active plan tracking
$hasActivePlan = false;
$activePlanName = "";
$activePlanMedalIcon = "";
$activePlanMedalClass = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Fetch user details including sponsor code
    $stmt = $pdo->prepare("SELECT full_name, balance, referral_code, created_at, sponsor FROM users WHERE email = ?");
    $stmt->execute([$userEmail]);
    $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);
    // Fetch PAN from user_banks table separately
    $panStmt = $pdo->prepare("SELECT pan FROM user_banks WHERE email = ? LIMIT 1");
    $panStmt->execute([$userEmail]);
    $bankData = $panStmt->fetch(PDO::FETCH_ASSOC);
    
    $userPan = ($bankData && !empty($bankData['pan'])) ? htmlspecialchars($bankData['pan']) : "";
    
    // Flag to check if KYC popup should show (true if PAN is empty/null)
    $showKycPopup = empty($userPan);
    
    if ($currentUser) {
        if (!empty($currentUser['full_name'])) {
            $fullName = htmlspecialchars($currentUser['full_name']);
        }
        if (isset($currentUser['balance'])) {
            $balance = number_format((float)$currentUser['balance'], 2, '.', '');
        }
        if (!empty($currentUser['referral_code'])) {
            $referralCode = htmlspecialchars($currentUser['referral_code']);
        }
        if (!empty($currentUser['created_at'])) {
        $registrationDate = date('Y-m-d', strtotime($currentUser['created_at']));
        } else {
        $registrationDate = date('Y-m-d');
        }
        
        // Fetch Sponsor's actual Full Name using the sponsor code/email stored in users.sponsor
        if (!empty($currentUser['sponsor'])) {
            $sponsorQuery = $pdo->prepare("SELECT full_name FROM users WHERE email = ? OR referral_code = ?");
            $sponsorQuery->execute([$currentUser['sponsor'], $currentUser['sponsor']]);
            $sponsorData = $sponsorQuery->fetch(PDO::FETCH_ASSOC);
            
            if ($sponsorData && !empty($sponsorData['full_name'])) {
                $sponsorName = htmlspecialchars($sponsorData['full_name']);
            } else {
                $sponsorName = htmlspecialchars($currentUser['sponsor']);
            }
        }
    }

    // Check if user has any active purchased investment plan
    $planStmt = $pdo->prepare("SELECT plan_name FROM user_plans WHERE email = ? AND status = 'active' ORDER BY id DESC LIMIT 1");
    $planStmt->execute([$userEmail]);
    $activePlanData = $planStmt->fetch(PDO::FETCH_ASSOC);

    if ($activePlanData && !empty($activePlanData['plan_name'])) {
        $hasActivePlan = true;
        $activePlanName = strtolower($activePlanData['plan_name']);

        // Determine Medal Icon & Colors based on Plan Name
        if (strpos($activePlanName, 'basic') !== false) {
            $activePlanMedalIcon = '<i class="fa-solid fa-seedling text-blue-400"></i>';
            $activePlanMedalClass = 'bg-blue-500/20 text-blue-400 border-blue-500/30';
            $activePlanDisplayTitle = 'Basic Plan';
        }elseif (strpos($activePlanName, 'starter') !== false || strpos($activePlanName, 'bronze') !== false) {
            $activePlanMedalIcon = '<i class="fa-solid fa-medal text-amber-500"></i>';
            $activePlanMedalClass = 'bg-amber-600/20 text-amber-400 border-amber-500/30';
            $activePlanDisplayTitle = 'Starter Plan';
        } elseif (strpos($activePlanName, 'silver') !== false) {
            $activePlanMedalIcon = '<i class="fa-solid fa-medal text-slate-300"></i>';
            $activePlanMedalClass = 'bg-slate-400/20 text-slate-300 border-slate-400/30';
            $activePlanDisplayTitle = 'Silver Plan';
        } elseif (strpos($activePlanName, 'gold') !== false) {
            $activePlanMedalIcon = '<i class="fa-solid fa-medal text-yellow-400"></i>';
            $activePlanMedalClass = 'bg-yellow-500/20 text-yellow-400 border-yellow-500/30';
            $activePlanDisplayTitle = 'Gold Plan';
        } elseif (strpos($activePlanName, 'platinum') !== false) {
            $activePlanMedalIcon = '<i class="fa-solid fa-medal text-cyan-400"></i>';
            $activePlanMedalClass = 'bg-cyan-500/20 text-cyan-400 border-cyan-500/30';
            $activePlanDisplayTitle = 'Platinum Plan';
        } elseif (strpos($activePlanName, 'diamond') !== false) {
            $activePlanMedalIcon = '<i class="fa-solid fa-gem text-purple-400"></i>';
            $activePlanMedalClass = 'bg-purple-500/20 text-purple-400 border-purple-500/30';
            $activePlanDisplayTitle = 'Diamond Plan';
        } elseif (strpos($activePlanName, 'elite') !== false) {
            $activePlanMedalIcon = '<i class="fa-solid fa-crown text-emerald-400"></i>';
            $activePlanMedalClass = 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30';
            $activePlanDisplayTitle = 'Elite Pro Plan';
        } else {
            $activePlanMedalIcon = '<i class="fa-solid fa-award text-brand-400"></i>';
            $activePlanMedalClass = 'bg-brand-500/20 text-brand-400 border-brand-500/30';
            $activePlanDisplayTitle = $activePlanData['plan_name'];
        }
    }

} catch (\PDOException $e) {
    // Fallback if DB fails
}

// Generate the referral link dynamically based on current domain/path
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$hostName = $_SERVER['HTTP_HOST'];
$referralLink = "$protocol://$hostName/fbay?code=$referralCode";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fbay Dashboard</title>
   <link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#2563eb">
<link rel="apple-touch-icon" href="icons/icon-192.png">
<script>
if ('serviceWorker' in navigator) {

    window.addEventListener('load', () => {

        navigator.serviceWorker.register('sw.js')
            .then(registration => {
                console.log("Fbay Service Worker Registered:", registration.scope);
            })
            .catch(error => {
                console.error("Fbay Service Worker Error:", error);
            });

    });

}
</script>

    <link rel="icon" type="image/png" href="download.png">
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- FontAwesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#fdf4f6',
                            500: '#e11d48',
                            600: '#be123c',
                            700: '#9f1239',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in {
            animation: fadeIn 0.4s ease-out forwards;
        }
    </style>
    <style>
@media print {
    /* Hide everything on the body by default */
    body * {
        visibility: hidden;
    }
    /* Only show the modal and its content */
    #welcomeLetterModal, #welcomeLetterModal * {
        visibility: visible;
    }
    /* Reset modal positioning for a clean print page */
    #welcomeLetterModal {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        height: auto;
        background: white !important;
        color: black !important;
        display: block !important;
    }
    #printableLetterArea {
        border: none !important;
        box-shadow: none !important;
        background: white !important;
        color: black !important;
    }
    /* Hide buttons inside the modal when printing */
    .print\:hidden {
        display: none !important;
    }
}
</style>

</head>
<body class="bg-gray-900 text-gray-100 font-sans pb-28 selection:bg-brand-500 selection:text-white">

    <!-- Top Header / Welcome Section -->
    <header class="p-5 flex justify-between items-center bg-gray-800/50 backdrop-blur-md sticky top-0 z-40 border-b border-gray-800">
        <div>
            <p class="text-xs text-gray-400 uppercase tracking-wider font-semibold">Welcome Back</p>
            <h1 class="text-xl font-bold text-white flex items-center gap-2">
                <?php echo $fullName; ?> <span class="inline-block animate-bounce">👋</span>
            </h1>
        </div>
        <div class="flex items-center gap-3">
            <button onclick="toggleNotifications()" class="w-10 h-10 rounded-full bg-gray-800 flex items-center justify-center text-gray-300 hover:text-white hover:bg-gray-700 transition relative">
                <i class="fa-regular fa-bell text-lg"></i>
                <span class="absolute top-2 right-2 w-2.5 h-2.5 bg-brand-500 rounded-full animate-ping"></span>
                <span class="absolute top-2 right-2 w-2.5 h-2.5 bg-brand-500 rounded-full"></span>
            </button>
            <a href="logout.php" title="Logout" class="w-10 h-10 rounded-full bg-red-600/20 text-red-400 hover:bg-red-600 hover:text-white flex items-center justify-center transition">
                <i class="fa-solid fa-power-off"></i>
            </a>
        </div>
    </header>

    <!-- Main Dynamic Content Container -->
    <main class="max-w-md mx-auto p-4 space-y-6">

        <!-- ================= HOME TAB VIEW ================= -->
        <div id="homeView" class="space-y-6 animate-fade-in">
<!-- Balance Card Section -->
<section class="bg-gradient-to-br from-gray-800 to-gray-800/80 border border-gray-700/60 rounded-3xl p-6 shadow-2xl relative overflow-hidden">
    <div class="absolute -right-10 -top-10 w-32 h-32 bg-brand-500/10 rounded-full blur-2xl pointer-events-none"></div>

    <p class="text-sm font-medium text-gray-400">Total Balance</p>
    <div class="flex items-baseline gap-2 mt-1">
        <h2 id="balanceDisplay" class="text-3xl font-extrabold tracking-tight text-white">Rs. <?php echo $balance; ?></h2>
        
        <!-- Dynamic Active / Inactive Tag -->
        <?php if ($hasActivePlan): ?>
            <span class="text-xs font-semibold text-emerald-400 bg-emerald-500/10 border border-emerald-500/20 px-2.5 py-0.5 rounded-full flex items-center gap-1 shadow-sm">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span> Active Plan
            </span>
        <?php else: ?>
            <span class="text-xs font-semibold text-red-400 bg-red-500/10 border border-red-500/20 px-2.5 py-0.5 rounded-full flex items-center gap-1 shadow-sm">
                <span class="w-1.5 h-1.5 rounded-full bg-red-400 animate-pulse"></span> Inactive
            </span>
        <?php endif; ?>
    </div>

    <div class="grid grid-cols-3 gap-2.5 mt-6">
        <button onclick="openDepositChannels()" class="flex items-center justify-center gap-1.5 bg-brand-600 hover:bg-brand-700 active:scale-95 text-white py-3 px-2 rounded-2xl font-semibold text-sm shadow-lg shadow-brand-600/30 transition transform duration-200 truncate">
            <i class="fa-solid fa-arrow-down-long"></i> Deposit
        </button>
        <button onclick="openWithdrawModal()" class="flex items-center justify-center gap-1.5 bg-gray-700 hover:bg-gray-600 active:scale-95 text-white py-3 px-2 rounded-2xl font-semibold text-sm transition transform duration-200 border border-gray-600 truncate">
            <i class="fa-solid fa-arrow-up-long"></i> Withdraw
        </button>
        <button onclick="openTransferModal()" class="flex items-center justify-center gap-1.5 bg-gray-700 hover:bg-gray-600 active:scale-95 text-white py-3 px-2 rounded-2xl font-semibold text-sm transition transform duration-200 border border-gray-600 truncate">
            <i class="fa-solid fa-right-left"></i> Transfer
        </button>
    </div>
</section>

            <!-- Fbay Company Clothing Branding & Extended Story Section -->
            <section class="space-y-4">
                <div class="flex justify-between items-center px-1">
                        <div class="flex items-center gap-2">
                            <div class="w-9 h-9 rounded-xl overflow-hidden flex items-center justify-center shadow-lg shadow-brand-500/30">
                                <img src="download.png" alt="FBAY Logo" class="w-full h-full object-cover">
                            </div>
                            <div>
                                <h3 class="font-bold text-white tracking-wide text-sm">FBAY GLOBAL APPAREL</h3>
                                <p class="text-[11px] text-gray-400">Redefining Urban Fashion & E-Commerce</p>
                            </div>  
                        </div>
                    <button onclick="openModal('welcomeLetterModal')" class="bg-brand-600/20 hover:bg-brand-600/30 border border-brand-500/30 text-brand-400 text-xs font-bold px-3 py-1.5 rounded-xl transition flex items-center gap-1.5 shadow-sm">
                        <i class="fa-solid fa-envelope-open-text"></i> Welcome Letter
                    </button>
                </div>

                <!-- Extended Rich Content Paragraph Box -->
                <div class="bg-gradient-to-br from-gray-800/80 to-gray-800/40 border border-gray-700/60 rounded-3xl p-5 shadow-xl space-y-3">
                    <div class="flex items-center gap-2 text-brand-400 text-xs font-semibold uppercase tracking-wider">
                        <i class="fa-solid fa-award"><span>Official Brand Vision</span></i>
                    </div>
                    <p class="text-xs text-gray-300 leading-relaxed">
                        Welcome to <strong class="text-white">FBAY</strong>, your premier destination for high-end streetwear and minimalist urban apparel. We combine cutting-edge textile innovation with sustainable manufacturing to bring you garments that don't just look exceptional, but empower your daily lifestyle. 
                    </p>
                    <p class="text-xs text-gray-400 leading-relaxed">
                        As part of our next-gen digital ecosystem, every member gains exclusive access to limited-edition drops, global community rewards, and streamlined peer-to-peer asset growth. Experience the pinnacle of modern retail and financial empowerment today.
                    </p>
                    <div class="pt-2 flex items-center justify-between border-t border-gray-700/50 text-[11px] text-gray-400">
                        <span><i class="fa-solid fa-shield-check text-emerald-400 mr-1"></i> Verified Enterprise</span>
                        <span class="text-brand-400 font-semibold cursor-pointer hover:underline" onclick="openModal('welcomeLetterModal')">Read Official Welcome Letter &rarr;</span>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="group relative bg-gray-800 border border-gray-700/50 rounded-2xl overflow-hidden shadow-md hover:border-brand-500/50 transition duration-300">
                        <div class="h-36 overflow-hidden bg-gray-700">
                            <img src="https://images.unsplash.com/photo-1521572267360-ee0c2909d518?w=500&q=80" alt="Streetwear Tee" class="w-full h-full object-cover group-hover:scale-110 transition duration-500">
                        </div>
                        <div class="p-3">
                            <p class="text-xs text-brand-400 font-medium">Streetwear</p>
                            <h4 class="text-sm font-bold text-white truncate">Classic Oversized Tee</h4>
                            <p class="text-xs font-semibold text-gray-300 mt-1">Rs. 45.00</p>
                        </div>
                    </div>

                    <div class="group relative bg-gray-800 border border-gray-700/50 rounded-2xl overflow-hidden shadow-md hover:border-brand-500/50 transition duration-300">
                        <div class="h-36 overflow-hidden bg-gray-700">
                            <img src="https://images.unsplash.com/photo-1556905055-8f358a7a47b2?w=500&q=80" alt="Winter Hoodie" class="w-full h-full object-cover group-hover:scale-110 transition duration-500">
                        </div>
                        <div class="p-3">
                            <p class="text-xs text-brand-400 font-medium">Outerwear</p>
                            <h4 class="text-sm font-bold text-white truncate">Urban Minimalist Hoodie</h4>
                            <p class="text-xs font-semibold text-gray-300 mt-1">Rs. 85.00</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Reward and Salary Dual Tab Switcher Section -->
            <section class="bg-gray-800/80 border border-gray-700/60 rounded-3xl p-4 shadow-xl space-y-4">
                <div class="flex bg-gray-900/80 p-1 rounded-2xl border border-gray-700/50">
                    <button onclick="switchRewardSalary('reward')" id="rewardTabBtn" class="flex-1 py-2 text-xs font-bold rounded-xl bg-brand-600 text-white transition shadow-md flex items-center justify-center gap-1.5">
                        <i class="fa-solid fa-gift"></i> Reward
                    </button>
                    <button onclick="switchRewardSalary('salary')" id="salaryTabBtn" class="flex-1 py-2 text-xs font-bold rounded-xl text-gray-400 hover:text-white transition flex items-center justify-center gap-1.5">
                        <i class="fa-solid fa-wallet"></i> Salary
                    </button>
                </div>

                <!-- Reward View Content -->
                <div id="rewardContent" class="space-y-3 animate-fade-in">
                    <div class="h-auto rounded-2xl overflow-hidden bg-gray-900 border border-gray-700/50 relative shadow-inner">
                        <img src="reward.jpg" alt="Reward Banner" onerror="this.src='https://images.unsplash.com/photo-1513151233558-d860c5398176?w=600&q=80'" class="w-full h-full object-cover">
                    </div>
                    <div class="flex justify-between items-center text-xs px-1 text-gray-400">
                        <span><i class="fa-solid fa-circle-check text-emerald-400 mr-1"></i> Active Daily Claims</span>
                        <button onclick="switchTab(document.querySelector('button[onclick*=\'Invite\']'), 'Invite')" class="text-brand-400 font-semibold hover:underline">Invite Friends &rarr;</button>
                    </div>
                </div>

                <!-- Salary View Content -->
                <div id="salaryContent" class="hidden space-y-3 animate-fade-in">
                    <div class="h-auto rounded-2xl overflow-hidden bg-gray-900 border border-gray-700/50 relative shadow-inner">
                        <img src="salary.jpg" alt="Salary Banner" onerror="this.src='https://images.unsplash.com/photo-1554224155-8d04cb21cd6c?w=600&q=80'" class="w-full h-full object-cover">
                    </div>
                    <div class="flex justify-between items-center text-xs px-1 text-gray-400">
                        <span><i class="fa-solid fa-shield-halved text-emerald-400 mr-1"></i> Guaranteed Payouts</span>
                        <button onclick="openTeamReport()" class="text-brand-400 font-semibold hover:underline">View Team Report &rarr;</button>
                    </div>
                </div>
            </section>
        </div>
<!-- Trip Popup Overlay -->
<div id="tripOverlay" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden animate-fade-in">
    <div class="relative max-w-sm w-full bg-gray-900 border border-gray-700 rounded-3xl overflow-hidden shadow-2xl p-2">
        <!-- Close Button -->
        <button onclick="closeTripPopup()" class="absolute top-4 right-4 z-10 bg-black/60 hover:bg-black text-white w-8 h-8 rounded-full flex items-center justify-center transition border border-gray-600">
            <i class="fa-solid fa-xmark text-sm"></i>
        </button>
        <!-- Image Banner -->
        <div class="rounded-2xl overflow-hidden bg-gray-950 relative">
            <img src="trip.jpg" alt="Trip Popup" onerror="this.src='https://images.unsplash.com/photo-1513151233558-d860c5398176?w=600&q=80'" class="w-full h-auto object-cover max-h-[70vh]">
        </div>
    </div>
</div>

<script>
    // Function to open the popup
    function openTripPopup() {
        const overlay = document.getElementById('tripOverlay');
        if (overlay) overlay.classList.remove('hidden');
    }

    // Function to close the popup
    function closeTripPopup() {
        const overlay = document.getElementById('tripOverlay');
        if (overlay) overlay.classList.add('hidden');
    }

    // Page load hone par popup dikhane ke liye
    document.addEventListener('DOMContentLoaded', () => {
        openTripPopup();
    });
</script>

<?php if (isset($showKycPopup) && $showKycPopup): ?>
<!-- KYC Notice Popup -->
<div id="kycNoticeOverlay" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 flex items-center justify-center p-4 animate-fade-in">
    <div class="relative max-w-sm w-full bg-gray-900 border border-gray-700 rounded-3xl overflow-hidden shadow-2xl p-6 text-center">
        <button onclick="document.getElementById('kycNoticeOverlay').style.display='none'" class="absolute top-4 right-4 z-10 bg-black/60 hover:bg-black text-white w-8 h-8 rounded-full flex items-center justify-center transition border border-gray-600">
            <i class="fa-solid fa-xmark text-sm"></i>
        </button>
        <div class="my-3">
            <div class="w-16 h-16 bg-amber-500/20 text-amber-400 border border-amber-500/30 rounded-full flex items-center justify-center mx-auto mb-4 text-2xl">
                <i class="fa-solid fa-shield-halved"></i>
            </div>
            <h3 class="text-xl font-bold text-white mb-2">Update KYC Required</h3>
            <p class="text-gray-400 text-sm mb-6">Please update your PAN for compliance and smooth transactions.</p>
            <button onclick="openPanModal()" class="block w-full py-3 bg-brand-500 hover:bg-brand-600 text-white font-semibold rounded-xl transition shadow-lg cursor-pointer">
                Update Now
            </button>
        </div>
    </div>
</div>

<!-- PAN Input Modal -->
<div id="panModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden animate-fade-in">
    <div class="relative max-w-sm w-full bg-gray-900 border border-gray-700 rounded-3xl overflow-hidden shadow-2xl p-6">
        <button onclick="closePanModal()" class="absolute top-4 right-4 z-10 bg-black/60 hover:bg-black text-white w-8 h-8 rounded-full flex items-center justify-center transition border border-gray-600">
            <i class="fa-solid fa-xmark text-sm"></i>
        </button>
        <div class="text-center mb-5">
            <h3 class="text-xl font-bold text-white mb-1">KYC Verification</h3>
            <p class="text-gray-400 text-sm">Enter your PAN card number below</p>
        </div>
        <form onsubmit="savePanDetails(event)">
            <div class="mb-4">
                <label class="block text-gray-400 text-xs font-semibold mb-2 text-left">PAN Number</label>
                <input type="text" id="panNumberInput" placeholder="e.g. ABCDE1234F" class="w-full bg-gray-950 border border-gray-700 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-brand-500 uppercase" required>
            </div>
            <button type="submit" class="w-full py-3 bg-brand-500 hover:bg-brand-600 text-white font-semibold rounded-xl transition shadow-lg">
                Submit PAN
            </button>
        </form>
    </div>
</div>
<?php endif; ?>


        <!-- ================= INVITE TAB VIEW ================= -->
<div id="inviteView" class="hidden space-y-6 animate-fade-in text-center">
    
    <!-- Referral Box Card -->
    <div class="bg-gray-800 border border-gray-700/60 rounded-3xl p-6 shadow-2xl space-y-5">
        <div>
            <h2 class="text-lg font-bold text-white">Invite Friends & Earn</h2>
            <p class="text-xs text-gray-400 mt-1">Share your referral code or QR code with your friends to join Fbay.</p>
        </div>

        <!-- QR Code Display -->
        <div class="flex justify-center bg-white p-4 rounded-2xl w-48 h-48 mx-auto shadow-inner">
            <img id="qrCodeImg" src="" alt="Referral QR Code" class="w-full h-full object-contain">
        </div>

        <!-- Referral Code Box -->
        <div class="space-y-1">
            <label class="text-xs text-gray-400 font-medium">Your Referral Code</label>
            <div class="bg-gray-900 border border-gray-700 rounded-xl py-3 px-4 flex justify-between items-center">
                <span id="refCodeText" class="text-brand-400 font-bold tracking-wider text-base"><?php echo $referralCode; ?></span>
                <button onclick="copyCode()" class="text-xs bg-gray-800 hover:bg-gray-700 text-gray-200 px-3 py-1.5 rounded-lg border border-gray-600 transition">
                    <i class="fa-regular fa-copy"></i> Copy Code
                </button>
            </div>
        </div>

        <!-- Referral Link Box -->
        <div class="space-y-1 text-left">
            <label class="text-xs text-gray-400 font-medium">Your Invite Link</label>
            <div class="bg-gray-900 border border-gray-700 rounded-xl p-3 flex flex-col gap-2">
                <input type="text" id="refLinkInput" readonly value="<?php echo $referralLink; ?>" class="bg-transparent text-xs text-gray-300 outline-none w-full select-all truncate">
                <div class="flex gap-2">
                    <button onclick="copyLink()" class="flex-1 bg-brand-600 hover:bg-brand-700 active:scale-95 text-white py-2.5 rounded-xl text-xs font-semibold shadow-md transition flex items-center justify-center gap-2">
                        <i class="fa-solid fa-link"></i> Copy Link
                    </button>
                    <button onclick="shareLink()" class="bg-gray-800 hover:bg-gray-700 active:scale-95 text-gray-200 px-4 py-2.5 rounded-xl text-xs font-semibold border border-gray-600 shadow-md transition flex items-center justify-center gap-1.5" title="Share Link">
                        <i class="fa-solid fa-share-nodes text-brand-400"></i> Share
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Team Overview Summary Card (Up to 4 Levels) -->
    <div class="bg-gray-800 border border-gray-700/60 rounded-3xl p-5 shadow-xl text-left space-y-4">
        <div class="flex justify-between items-center">
            <h3 class="text-sm font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-users text-brand-400"></i> Team Summary & Levels
            </h3>
            <span class="text-[10px] text-gray-400 bg-gray-900 px-2.5 py-1 rounded-full border border-gray-700">4 Levels</span>
        </div>

        <!-- Table Container -->
        <div class="overflow-x-auto rounded-xl border border-gray-700/50">
            <table class="w-full text-left text-xs text-gray-300">
                <thead class="bg-gray-900 text-gray-400 uppercase text-[10px] tracking-wider border-b border-gray-700">
                    <tr>
                        <th class="py-2.5 px-3">Level</th>
                        <th class="py-2.5 px-3">Total Members</th>
                        <th class="py-2.5 px-3">Active / Inactive</th>
                        <th class="py-2.5 px-3">Plan Amount</th>
                    </tr>
                </thead>
                <tbody id="inviteTeamTableBody" class="divide-y divide-gray-700/50 bg-gray-900/50">
                    <tr>
                        <td colspan="4" class="text-center py-4 text-gray-400">Loading team summary...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Salary Conditions & Progress Card -->
    <div class="bg-gray-800 border border-gray-700/60 rounded-3xl p-5 shadow-xl text-left space-y-5">
        <div class="flex justify-between items-center">
            <h3 class="text-sm font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-award text-brand-400"></i> Salary Conditions & Progress
            </h3>
            <span class="text-[10px] text-brand-400 bg-brand-500/10 border border-brand-500/20 px-2.5 py-1 rounded-full font-semibold">Active Plan: <span id="userCurrentPlan" class="text-white">Checking...</span></span>
        </div>

        <!-- Monthly Salary Conditions -->
        <div class="space-y-2.5">
            <p class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">Monthly Salary Conditions <span class="text-[9px] text-gray-500 font-normal">(Tap card to view progress)</span></p>
            <div id="monthlySalaryContainer" class="space-y-2">
                <div class="text-xs text-gray-400 text-center py-2">Loading monthly conditions...</div>
            </div>
        </div>

        <!-- Weekly Salary / Referral Conditions -->
        <div class="space-y-2.5 pt-2 border-t border-gray-700/50">
            <p class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">Weekly Salary Conditions <span class="text-[9px] text-gray-500 font-normal">(Mon & Tue Referral Rule)</span></p>
            <div id="weeklySalaryContainer" class="space-y-2">
                <div class="text-xs text-gray-400 text-center py-2">Loading weekly conditions...</div>
            </div>
        </div>
    </div>

    <!-- Salary Progress Modal -->
    <div id="salaryModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-gray-900 border border-gray-700 rounded-3xl w-full max-w-sm p-6 space-y-4 shadow-2xl relative">
            <div class="flex justify-between items-center border-b border-gray-800 pb-3">
                <h4 id="modalTitle" class="text-sm font-bold text-white">Salary Progress Details</h4>
                <button onclick="closeSalaryModal()" class="text-gray-400 hover:text-white text-sm font-bold bg-gray-800 w-7 h-7 rounded-full flex items-center justify-center">✕</button>
            </div>
            <div id="modalBody" class="space-y-3 text-xs text-gray-300">
                <!-- Dynamic Progress Breakdown -->
            </div>
            <button onclick="closeSalaryModal()" class="w-full py-2.5 bg-brand-500 hover:bg-brand-600 text-white font-bold rounded-xl text-xs transition">Got It</button>
        </div>
    </div>

</div>

<!-- ================= PLANS TAB VIEW ================= -->
        <div id="plansView" class="hidden space-y-4 animate-fade-in py-4">
            <div class="text-center mb-6">
                <h3 class="text-lg font-bold text-white flex items-center justify-center gap-2">
                    <i class="fa-solid fa-layer-group text-brand-500"></i> Investment Plans
                </h3>
                <p class="text-xs text-gray-400 mt-1">Choose a plan to grow your daily earnings.</p>
            </div>

            <!-- 7 Investment Plans Cards Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                
                <!-- Plan 1: Basic (Dynamic Backend Synced) -->
<div id="basicPlanCard" class="bg-gray-800 border border-gray-700/80 rounded-3xl p-5 shadow-xl flex flex-col justify-between space-y-4 relative overflow-hidden transition-all duration-300">
    
    <!-- Sold Out Overlay Badge -->
    <div id="soldOutOverlay" class="absolute inset-0 bg-gray-950/80 backdrop-blur-[2px] z-20 flex items-center justify-center hidden">
        <span class="bg-red-600 text-white font-black text-lg px-8 py-2 rounded-xl uppercase tracking-widest transform -rotate-12 shadow-2xl border-2 border-red-400">Sold Out</span>
    </div>

    <div class="absolute top-0 right-0 bg-blue-500/20 text-blue-400 text-[10px] font-bold px-3 py-1 rounded-bl-xl border-l border-b border-blue-500/30 flex items-center gap-1">
        <span class="inline-block w-1.5 h-1.5 rounded-full bg-blue-400 animate-pulse"></span> New Badge
    </div>
    
    <div class="space-y-1">
        <h4 class="text-white font-bold text-base flex items-center gap-2"><i class="fa-solid fa-seedling text-blue-400"></i> Basic Plan</h4>
        <p class="text-xs text-gray-400">Starter friendly returns</p>
    </div>
    
    <!-- Countdown Timer Element -->
    <div class="bg-blue-950/40 border border-blue-500/30 rounded-xl p-2.5 flex items-center justify-between text-xs">
        <span class="text-blue-300 flex items-center gap-1.5"><i class="fa-regular fa-clock"></i> Limited Offer:</span>
        <span id="basicPlanTimer" class="font-mono font-bold text-blue-400 tracking-wider">Syncing...</span>
    </div>

    <div class="bg-gray-900/60 p-3 rounded-2xl border border-gray-700/50 space-y-1.5 text-xs">
        <div class="flex justify-between"><span class="text-gray-400">Investment:</span><span class="text-white font-bold">Rs. 800</span></div>
        <div class="flex justify-between"><span class="text-gray-400">Daily Benefit:</span><span class="text-emerald-400 font-bold">+Rs. 35.00</span></div>
    </div>
    
    <button id="basicPlanBtn" onclick="openBuyPlanModal('Basic Plan', 800, 35)" class="w-full bg-brand-600 hover:bg-brand-700 active:scale-95 text-white py-2.5 rounded-xl text-xs font-semibold transition shadow-lg shadow-brand-600/30">Buy Now</button>
</div>

                <!-- Plan 2: Starter / Bronze -->
                <div class="bg-gray-800 border border-gray-700/80 rounded-3xl p-5 shadow-xl flex flex-col justify-between space-y-4 relative overflow-hidden">
                    <div class="absolute top-0 right-0 bg-amber-600/20 text-amber-400 text-[10px] font-bold px-3 py-1 rounded-bl-xl border-l border-b border-amber-500/30">Bronze Medal</div>
                    <div class="space-y-1">
                        <h4 class="text-white font-bold text-base flex items-center gap-2"><i class="fa-solid fa-medal text-amber-500"></i> Starter Plan</h4>
                        <p class="text-xs text-gray-400">Daily stable returns</p>
                    </div>
                    <div class="bg-gray-900/60 p-3 rounded-2xl border border-gray-700/50 space-y-1.5 text-xs">
                        <div class="flex justify-between"><span class="text-gray-400">Investment:</span><span class="text-white font-bold">Rs. 1,000</span></div>
                        <div class="flex justify-between"><span class="text-gray-400">Daily Benefit:</span><span class="text-emerald-400 font-bold">+Rs. 62.50</span></div>
                    </div>
                    <button onclick="openBuyPlanModal('Starter Plan', 1000, 62.5)" class="w-full bg-brand-600 hover:bg-brand-700 active:scale-95 text-white py-2.5 rounded-xl text-xs font-semibold transition shadow-lg shadow-brand-600/30">Buy Now</button>
                </div>

                <!-- Plan 3: Silver -->
                <div class="bg-gray-800 border border-gray-700/80 rounded-3xl p-5 shadow-xl flex flex-col justify-between space-y-4 relative overflow-hidden">
                    <div class="absolute top-0 right-0 bg-slate-400/20 text-slate-300 text-[10px] font-bold px-3 py-1 rounded-bl-xl border-l border-b border-slate-400/30">Silver Medal</div>
                    <div class="space-y-1">
                        <h4 class="text-white font-bold text-base flex items-center gap-2"><i class="fa-solid fa-medal text-slate-300"></i> Silver Plan</h4>
                        <p class="text-xs text-gray-400">Daily stable returns</p>
                    </div>
                    <div class="bg-gray-900/60 p-3 rounded-2xl border border-gray-700/50 space-y-1.5 text-xs">
                        <div class="flex justify-between"><span class="text-gray-400">Investment:</span><span class="text-white font-bold">Rs. 1,800</span></div>
                        <div class="flex justify-between"><span class="text-gray-400">Daily Benefit:</span><span class="text-emerald-400 font-bold">+Rs. 100.00</span></div>
                    </div>
                    <button onclick="openBuyPlanModal('Silver Plan', 1800, 100)" class="w-full bg-brand-600 hover:bg-brand-700 active:scale-95 text-white py-2.5 rounded-xl text-xs font-semibold transition shadow-lg shadow-brand-600/30">Buy Now</button>
                </div>

                <!-- Plan 4: Gold -->
                <div class="bg-gray-800 border border-gray-700/80 rounded-3xl p-5 shadow-xl flex flex-col justify-between space-y-4 relative overflow-hidden">
                    <div class="absolute top-0 right-0 bg-yellow-500/20 text-yellow-400 text-[10px] font-bold px-3 py-1 rounded-bl-xl border-l border-b border-yellow-500/30">Gold Medal</div>
                    <div class="space-y-1">
                        <h4 class="text-white font-bold text-base flex items-center gap-2"><i class="fa-solid fa-medal text-yellow-400"></i> Gold Plan</h4>
                        <p class="text-xs text-gray-400">Daily stable returns</p>
                    </div>
                    <div class="bg-gray-900/60 p-3 rounded-2xl border border-gray-700/50 space-y-1.5 text-xs">
                        <div class="flex justify-between"><span class="text-gray-400">Investment:</span><span class="text-white font-bold">Rs. 2,500</span></div>
                        <div class="flex justify-between"><span class="text-gray-400">Daily Benefit:</span><span class="text-emerald-400 font-bold">+Rs. 125.00</span></div>
                    </div>
                    <button onclick="openBuyPlanModal('Gold Plan', 2500, 125)" class="w-full bg-brand-600 hover:bg-brand-700 active:scale-95 text-white py.2.5 rounded-xl text-xs font-semibold transition shadow-lg shadow-brand-600/30">Buy Now</button>
                </div>

                <!-- Plan 5: Platinum -->
                <div class="bg-gray-800 border border-gray-700/80 rounded-3xl p-5 shadow-xl flex flex-col justify-between space-y-4 relative overflow-hidden">
                    <div class="absolute top-0 right-0 bg-cyan-500/20 text-cyan-400 text-[10px] font-bold px-3 py-1 rounded-bl-xl border-l border-b border-cyan-500/30">Platinum Medal</div>
                    <div class="space-y-1">
                        <h4 class="text-white font-bold text-base flex items-center gap-2"><i class="fa-solid fa-medal text-cyan-400"></i> Platinum Plan</h4>
                        <p class="text-xs text-gray-400">High performance returns</p>
                    </div>
                    <div class="bg-gray-900/60 p-3 rounded-2xl border border-gray-700/50 space-y-1.5 text-xs">
                        <div class="flex justify-between"><span class="text-gray-400">Investment:</span><span class="text-white font-bold">Rs. 6,000</span></div>
                        <div class="flex justify-between"><span class="text-gray-400">Daily Benefit:</span><span class="text-emerald-400 font-bold">+Rs. 200.00</span></div>
                    </div>
                    <button onclick="openBuyPlanModal('Platinum Plan', 6000, 200)" class="w-full bg-brand-600 hover:bg-brand-700 active:scale-95 text-white py-2.5 rounded-xl text-xs font-semibold transition shadow-lg shadow-brand-600/30">Buy Now</button>
                </div>

                <!-- Plan 6: Diamond -->
                <div class="bg-gray-800 border border-gray-700/80 rounded-3xl p-5 shadow-xl flex flex-col justify-between space-y-4 relative overflow-hidden">
                    <div class="absolute top-0 right-0 bg-purple-500/20 text-purple-400 text-[10px] font-bold px-3 py-1 rounded-bl-xl border-l border-b border-purple-500/30">Diamond Medal</div>
                    <div class="space-y-1">
                        <h4 class="text-white font-bold text-base flex items-center gap-2"><i class="fa-solid fa-gem text-purple-400"></i> Diamond Plan</h4>
                        <p class="text-xs text-gray-400">High performance returns</p>
                    </div>
                    <div class="bg-gray-900/60 p-3 rounded-2xl border border-gray-700/50 space-y-1.5 text-xs">
                        <div class="flex justify-between"><span class="text-gray-400">Investment:</span><span class="text-white font-bold">Rs. 13,000</span></div>
                        <div class="flex justify-between"><span class="text-gray-400">Daily Benefit:</span><span class="text-emerald-400 font-bold">+Rs. 260.00</span></div>
                    </div>
                    <button onclick="openBuyPlanModal('Diamond Plan', 13000, 260)" class="w-full bg-brand-600 hover:bg-brand-700 active:scale-95 text-white py-2.5 rounded-xl text-xs font-semibold transition shadow-lg shadow-brand-600/30">Buy Now</button>
                </div>

                <!-- Plan 7: Elite Pro -->
                <div class="bg-gray-800 border border-gray-700/80 rounded-3xl p-5 shadow-xl flex flex-col justify-between space-y-4 relative overflow-hidden">
                    <div class="absolute top-0 right-0 bg-emerald-500/20 text-emerald-400 text-[10px] font-bold px-3 py-1 rounded-bl-xl border-l border-b border-emerald-500/30">Elite Pro Medal</div>
                    <div class="space-y-1">
                        <h4 class="text-white font-bold text-base flex items-center gap-2"><i class="fa-solid fa-crown text-emerald-400"></i> Elite Pro Plan</h4>
                        <p class="text-xs text-gray-400">Maximum tier returns</p>
                    </div>
                    <div class="bg-gray-900/60 p-3 rounded-2xl border border-gray-700/50 space-y-1.5 text-xs">
                        <div class="flex justify-between"><span class="text-gray-400">Investment:</span><span class="text-white font-bold">Rs. 25,000</span></div>
                        <div class="flex justify-between"><span class="text-gray-400">Daily Benefit:</span><span class="text-emerald-400 font-bold">+Rs. 500.00</span></div>
                    </div>
                    <button onclick="openBuyPlanModal('Elite Pro Plan', 25000, 500)" class="w-full bg-brand-600 hover:bg-brand-700 active:scale-95 text-white py-2.5 rounded-xl text-xs font-semibold transition shadow-lg shadow-brand-600/30">Buy Now</button>
                </div>

            </div>
        </div>

<!-- JavaScript to run the 72-hour countdown -->
<script>
    async function initBasicPlanTimer() {
    const timerDisplay = document.getElementById('basicPlanTimer');
    const cardElement = document.getElementById('basicPlanCard');
    const overlayElement = document.getElementById('soldOutOverlay');
    const buyButton = document.getElementById('basicPlanBtn');
    
    if (!timerDisplay) return;

    try {
        // Fetch real-time status from backend PHP file
        const response = await fetch('check-status.php');
        const data = await response.json();

        let totalSeconds = data.time_left;

        if (data.expired || totalSeconds <= 0) {
            timerDisplay.textContent = "EXPIRED";
            cardElement.classList.add('opacity-50', 'pointer-events-none', 'grayscale');
            overlayElement.classList.remove('hidden');
            if (buyButton) buyButton.disabled = true;
            return;
        }

        const countdown = setInterval(() => {
            if (totalSeconds <= 0) {
                clearInterval(countdown);
                timerDisplay.textContent = "EXPIRED";
                cardElement.classList.add('opacity-50', 'pointer-events-none', 'grayscale');
                overlayElement.classList.remove('hidden');
                if (buyButton) buyButton.disabled = true;
                return;
            }

            totalSeconds--;

            const hours = Math.floor(totalSeconds / 3600);
            const minutes = Math.floor((totalSeconds % 3600) / 60);
            const seconds = totalSeconds % 60;

            timerDisplay.textContent = 
                String(hours).padStart(2, '0') + ":" + 
                String(minutes).padStart(2, '0') + ":" + 
                String(seconds).padStart(2, '0');
        }, 1000);

    } catch (error) {
        console.error("Failed to sync timer with backend:", error);
        timerDisplay.textContent = "Error";
    }
}

// Run on page load
initBasicPlanTimer();
</script>

        <!-- ================= TASK TAB VIEW ================= -->
        <div id="taskView" class="hidden space-y-4 animate-fade-in py-4">
            <div class="text-center mb-6">
                <h3 class="text-lg font-bold text-white flex items-center justify-center gap-2">
                    <i class="fa-solid fa-list-check text-brand-500"></i> My Investment Plans
                </h3>
                <p class="text-xs text-gray-400 mt-1">Manage your active investments and check past history.</p>
            </div>

            <!-- Active Plans Section -->
            <div class="space-y-3">
                <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider px-1">Active Plan</h4>
                <div id="activePlansContainer">
                    <!-- Dynamic Active Plan will load here via JS -->
                    <div class="bg-gray-800/50 border border-dashed border-gray-700 rounded-3xl p-6 text-center text-gray-400 text-xs">
                        No active investment plan found.
                    </div>
                </div>
            </div>

            <!-- Completed Plans Section -->
            <div class="space-y-3 pt-4">
                <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider px-1">Completed History</h4>
                <div class="bg-gray-800 border border-gray-700/80 rounded-3xl p-4 shadow-xl overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-gray-700 text-gray-400">
                                <th class="pb-2 font-medium">Plan Name</th>
                                <th class="pb-2 font-medium">Amount</th>
                                <th class="pb-2 font-medium">Revenue</th>
                                <th class="pb-2 font-medium text-right">Date</th>
                            </tr>
                        </thead>
                        <tbody id="completedPlansTableBody">
                            <!-- Dynamic Completed Plans will load here via JS -->
                            <tr>
                                <td colspan="4" class="text-center py-4 text-gray-500">No completed plans yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
                        
        <!-- ================= SETTING TAB VIEW ================= -->
        <div id="settingView" class="hidden space-y-6 animate-fade-in">
            <!-- Profile Overview Header Card -->
            <div class="bg-gradient-to-r from-gray-800 to-gray-800/90 border border-gray-700/60 rounded-3xl p-5 shadow-xl flex items-center justify-between">
                <div class="flex items-center gap-4 overflow-hidden">
                    <div class="w-14 h-14 rounded-2xl bg-gradient-to-tr from-brand-600 to-brand-500 flex items-center justify-center text-white text-2xl font-bold shadow-lg shadow-brand-500/20 shrink-0">
                        <?php echo substr($fullName, 0, 1); ?>
                    </div>
                    <div class="overflow-hidden">
                        <h3 class="font-bold text-white text-base truncate"><?php echo $fullName; ?></h3>
                        <p class="text-xs text-gray-400 truncate"><?php echo htmlspecialchars($userEmail); ?></p>
                        <span class="inline-block mt-1 text-[10px] font-semibold bg-brand-500/10 text-brand-400 border border-brand-500/20 px-2 py-0.5 rounded-full">
                            Ref: <?php echo $referralCode; ?>
                        </span>
                    </div>
                </div>

                <!-- Active Plan Medal Badge or Inactive State on the Right -->
                <div class="shrink-0 ml-2 text-right">
                    <?php if ($hasActivePlan): ?>
                        <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl border <?php echo $activePlanMedalClass; ?> text-xs font-bold shadow-sm">
                            <?php echo $activePlanMedalIcon; ?>
                            <span><?php echo $activePlanDisplayTitle; ?></span>
                        </div>
                        <span class="text-[9px] text-emerald-400 font-semibold block mt-1">● Plan Active</span>
                    <?php else: ?>
                        <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl border bg-red-500/20 text-red-400 border-red-500/30 text-xs font-bold shadow-sm">
                            <i class="fa-solid fa-triangle-exclamation text-red-400"></i>
                            <span>Inactive</span>
                        </div>
                        <span class="text-[9px] text-red-400 font-semibold block mt-1">● No Active Plan</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Settings Options List -->
            <div class="bg-gray-800/60 backdrop-blur-md border border-gray-700/60 rounded-3xl p-4 shadow-xl space-y-2">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider px-3 pb-1">Preferences & Security</p>
                
                <button onclick="openModal('accountDetailsModal')" class="w-full group flex items-center justify-between bg-gray-900/50 hover:bg-gray-900 border border-gray-800 hover:border-gray-700 p-3.5 rounded-2xl transition duration-200">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-blue-500/10 text-blue-400 flex items-center justify-center group-hover:scale-105 transition"><i class="fa-solid fa-user text-sm"></i></div>
                        <span class="text-xs font-semibold text-gray-200">Account Details</span>
                    </div>
                    <i class="fa-solid fa-chevron-right text-gray-500 text-[10px] group-hover:translate-x-0.5 transition"></i>
                </button>

                <button onclick="openBankModal()" class="w-full group flex items-center justify-between bg-gray-900/50 hover:bg-gray-900 border border-gray-800 hover:border-gray-700 p-3.5 rounded-2xl transition duration-200">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center group-hover:scale-105 transition"><i class="fa-solid fa-building-columns text-sm"></i></div>
                        <span class="text-xs font-semibold text-gray-200">Bind Bank Card</span>
                    </div>
                    <i class="fa-solid fa-chevron-right text-gray-500 text-[10px] group-hover:translate-x-0.5 transition"></i>
                </button>

                <button onclick="openTeamReport()" class="w-full group flex items-center justify-between bg-gray-900/50 hover:bg-gray-900 border border-gray-800 hover:border-gray-700 p-3.5 rounded-2xl transition duration-200">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-purple-500/10 text-purple-400 flex items-center justify-center group-hover:scale-105 transition"><i class="fa-solid fa-users text-sm"></i></div>
                        <span class="text-xs font-semibold text-gray-200">Team Report</span>
                    </div>
                    <i class="fa-solid fa-chevron-right text-gray-500 text-[10px] group-hover:translate-x-0.5 transition"></i>
                </button>

                <button onclick="openFinancialRecords()" class="w-full group flex items-center justify-between bg-gray-900/50 hover:bg-gray-900 border border-gray-800 hover:border-gray-700 p-3.5 rounded-2xl transition duration-200">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-amber-500/10 text-amber-400 flex items-center justify-center group-hover:scale-105 transition"><i class="fa-solid fa-receipt text-sm"></i></div>
                        <span class="text-xs font-semibold text-gray-200">Financial Records</span>
                    </div>
                    <i class="fa-solid fa-chevron-right text-gray-500 text-[10px] group-hover:translate-x-0.5 transition"></i>
                </button>

                <button onclick="openModal('changePasswordModal')" class="w-full group flex items-center justify-between bg-gray-900/50 hover:bg-gray-900 border border-gray-800 hover:border-gray-700 p-3.5 rounded-2xl transition duration-200">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-brand-500/10 text-brand-400 flex items-center justify-center group-hover:scale-105 transition"><i class="fa-solid fa-key text-sm"></i></div>
                        <span class="text-xs font-semibold text-gray-200">Change Password</span>
                    </div>
                    <i class="fa-solid fa-chevron-right text-gray-500 text-[10px] group-hover:translate-x-0.5 transition"></i>
                </button>

                <a href="logout.php" class="w-full group flex items-center justify-between bg-red-600/10 hover:bg-red-600/20 border border-red-500/20 p-3.5 rounded-2xl transition duration-200 mt-2">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-red-500/20 text-red-400 flex items-center justify-center group-hover:scale-105 transition"><i class="fa-solid fa-power-off text-sm"></i></div>
                        <span class="text-xs font-semibold text-red-400">Logout Account</span>
                    </div>
                    <i class="fa-solid fa-chevron-right text-red-400 text-[10px] group-hover:translate-x-0.5 transition"></i>
                </a>
            </div>
        </div>

    </main>

    <!-- ================= MODALS OVERLAYS ================= -->

    <!-- Deposit Channels Selection Modal -->
    <div id="depositChannelsModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-gray-800 border border-gray-700 rounded-3xl p-6 w-full max-w-sm space-y-4 animate-fade-in shadow-2xl">
            <div class="flex justify-between items-center border-b border-gray-700 pb-3">
                <h4 class="font-bold text-white text-base">Select Payment Channel</h4>
                <button onclick="closeModal('depositChannelsModal')" class="text-gray-400 hover:text-white"><i class="fa-solid fa-xmark text-lg"></i></button>
            </div>
            <div class="grid grid-cols-2 gap-3 text-xs">
                <button onclick="selectChannel('Channel 1')" class="bg-gray-900 border border-gray-700 hover:border-brand-500 p-4 rounded-2xl flex flex-col items-center gap-2 transition">
                    <i class="fa-solid fa-qrcode text-xl text-brand-500"></i>
                    <span class="font-semibold text-white">Channel 1</span>
                </button>
                <button onclick="selectChannel('Channel 2')" class="bg-gray-900 border border-gray-700 hover:border-brand-500 p-4 rounded-2xl flex flex-col items-center gap-2 transition">
                    <i class="fa-solid fa-wallet text-xl text-brand-500"></i>
                    <span class="font-semibold text-white">Channel 2</span>
                </button>
                <button onclick="selectChannel('Channel 3')" class="bg-gray-900 border border-gray-700 hover:border-brand-500 p-4 rounded-2xl flex flex-col items-center gap-2 transition">
                    <i class="fa-solid fa-building-columns text-xl text-brand-500"></i>
                    <span class="font-semibold text-white">Channel 3</span>
                </button>
                <button onclick="selectChannel('Channel 4')" class="bg-gray-900 border border-gray-700 hover:border-brand-500 p-4 rounded-2xl flex flex-col items-center gap-2 transition">
                    <i class="fa-solid fa-credit-card text-xl text-brand-500"></i>
                    <span class="font-semibold text-white">Channel 4</span>
                </button>
                <button onclick="selectChannel('Channel 5')" class="bg-gray-900 border border-gray-700 hover:border-brand-500 p-4 rounded-2xl flex flex-col items-center gap-2 transition">
                    <i class="fa-solid fa-globe text-xl text-brand-500"></i>
                    <span class="font-semibold text-white">Channel 5</span>
                </button>
                <button onclick="selectChannel('Channel 6')" class="bg-gray-900 border border-gray-700 hover:border-brand-500 p-4 rounded-2xl flex flex-col items-center gap-2 transition">
                    <i class="fa-solid fa-shield-halved text-xl text-brand-500"></i>
                    <span class="font-semibold text-white">Channel 6</span>
                </button>
            </div>
            <button onclick="closeModal('depositChannelsModal')" class="w-full bg-gray-700 hover:bg-gray-600 text-white py-3 rounded-xl text-xs font-semibold transition mt-2">Cancel</button>
        </div>
    </div>

    <!-- Deposit Form & QR Code Modal -->
    <div id="depositFormModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-gray-800 border border-gray-700 rounded-3xl p-6 w-full max-w-sm space-y-4 animate-fade-in shadow-2xl max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center border-b border-gray-700 pb-3">
                <h4 id="depositModalTitle" class="font-bold text-white text-base">Deposit Funds</h4>
                <button onclick="closeModal('depositFormModal')" class="text-gray-400 hover:text-white"><i class="fa-solid fa-xmark text-lg"></i></button>
            </div>

            <div class="space-y-4 text-xs">
                <!-- QR Code Display -->
                <div class="flex justify-center bg-white p-3 rounded-2xl w-36 h-36 mx-auto shadow-inner">
                    <img id="depositQrImg" src="" alt="UPI QR Code" class="w-full h-full object-contain">
                </div>

                <!-- UPI Address Box -->
                <div class="space-y-1">
                    <label class="text-gray-400">UPI Address</label>
                    <div class="bg-gray-900 border border-gray-700 rounded-xl p-3 flex justify-between items-center">
                        <span id="upiIdText" class="text-brand-400 font-bold select-all">ranaqamar15451-2@okhdfcbank</span>
                        <button onclick="copyUpi()" class="bg-gray-800 hover:bg-gray-700 text-gray-200 px-2.5 py-1 rounded-lg border border-gray-600">Copy</button>
                    </div>
                </div>

                <!-- Preset Amount Buttons -->
                <div class="space-y-1.5">
                    <label class="text-gray-400">Select Amount (Rs.)</label>
                    <div class="grid grid-cols-3 gap-2">
    <button type="button" onclick="setDepositAmount(200)" class="preset-btn bg-gray-900 hover:bg-gray-700 border border-gray-700 py-2.5 rounded-xl font-bold text-white transition">200</button>
    <button type="button" onclick="setDepositAmount(300)" class="preset-btn bg-gray-900 hover:bg-gray-700 border border-gray-700 py-2.5 rounded-xl font-bold text-white transition">300</button>
    <button type="button" onclick="setDepositAmount(500)" class="preset-btn bg-gray-900 hover:bg-gray-700 border border-gray-700 py-2.5 rounded-xl font-bold text-white transition">500</button>

    <button type="button" onclick="setDepositAmount(600)" class="preset-btn bg-gray-900 hover:bg-gray-700 border border-gray-700 py-2.5 rounded-xl font-bold text-white transition">600</button>
    <button type="button" onclick="setDepositAmount(800)" class="preset-btn bg-gray-900 hover:bg-gray-700 border border-gray-700 py-2.5 rounded-xl font-bold text-white transition">800</button>
    <button type="button" onclick="setDepositAmount(1000)" class="preset-btn bg-gray-900 hover:bg-gray-700 border border-gray-700 py-2.5 rounded-xl font-bold text-white transition">1000</button>

    <button type="button" onclick="setDepositAmount(1100)" class="preset-btn bg-gray-900 hover:bg-gray-700 border border-gray-700 py-2.5 rounded-xl font-bold text-white transition">1100</button>
    <button type="button" onclick="setDepositAmount(1400)" class="preset-btn bg-gray-900 hover:bg-gray-700 border border-gray-700 py-2.5 rounded-xl font-bold text-white transition">1400</button>
    <button type="button" onclick="setDepositAmount(1500)" class="preset-btn bg-gray-900 hover:bg-gray-700 border border-gray-700 py-2.5 rounded-xl font-bold text-white transition">1500</button>

    <button type="button" onclick="setDepositAmount(2000)" class="preset-btn bg-gray-900 hover:bg-gray-700 border border-gray-700 py-2.5 rounded-xl font-bold text-white transition">2000</button>
    <button type="button" onclick="setDepositAmount(2500)" class="preset-btn bg-gray-900 hover:bg-gray-700 border border-gray-700 py-2.5 rounded-xl font-bold text-white transition">2500</button>
    <button type="button" onclick="setDepositAmount(4000)" class="preset-btn bg-gray-900 hover:bg-gray-700 border border-gray-700 py-2.5 rounded-xl font-bold text-white transition">4000</button>
    <button type="button" onclick="setDepositAmount(5000)" class="preset-btn bg-gray-900 hover:bg-gray-700 border border-gray-700 py-2.5 rounded-xl font-bold text-white transition">5000</button>

    <button type="button" onclick="setDepositAmount(6000)" class="preset-btn bg-gray-900 hover:bg-gray-700 border border-gray-700 py-2.5 rounded-xl font-bold text-white transition">6000</button>
    <button type="button" onclick="setDepositAmount(7000)" class="preset-btn bg-gray-900 hover:bg-gray-700 border border-gray-700 py-2.5 rounded-xl font-bold text-white transition">7000</button>
    <button type="button" onclick="setDepositAmount(8000)" class="preset-btn bg-gray-900 hover:bg-gray-700 border border-gray-700 py-2.5 rounded-xl font-bold text-white transition">8000</button>
    <button type="button" onclick="setDepositAmount(9000)" class="preset-btn bg-gray-900 hover:bg-gray-700 border border-gray-700 py-2.5 rounded-xl font-bold text-white transition">9000</button>
    <button type="button" onclick="setDepositAmount(10000)" class="preset-btn bg-gray-900 hover:bg-gray-700 border border-gray-700 py-2.5 rounded-xl font-bold text-white transition">10000</button>
                        </div>
                </div>

                <!-- Form Inputs -->
                <form id="depositSubmitForm" onsubmit="submitDeposit(event)" class="space-y-3">
                    <div>
                        <label class="text-gray-400">Amount (Strictly selected from above)</label>
                        <input type="number" id="depositAmountInput" required readonly placeholder="Select amount above" class="w-full mt-1 bg-gray-900/70 border border-gray-700 rounded-xl p-3 text-brand-400 font-bold outline-none cursor-not-allowed">
                    </div>
                    <div>
                        <label class="text-gray-400">UTR / Transaction Reference Number</label>
                        <input type="text" id="depositUtrInput" required placeholder="Enter UTR / Ref number" class="w-full mt-1 bg-gray-900 border border-gray-700 rounded-xl p-3 text-white outline-none focus:border-brand-500">
                    </div>
                    <button type="submit" class="w-full bg-brand-600 hover:bg-brand-700 text-white py-3 rounded-xl text-xs font-semibold transition mt-2 shadow-lg shadow-brand-600/30">Submit Deposit</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Withdraw Modal -->
    <div id="withdrawModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-gray-800 border border-gray-700 rounded-3xl p-6 w-full max-w-sm space-y-4 animate-fade-in shadow-2xl max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center border-b border-gray-700 pb-3">
                <h4 class="font-bold text-white text-base">Withdraw Funds</h4>
                <button onclick="closeModal('withdrawModal')" class="text-gray-400 hover:text-white"><i class="fa-solid fa-xmark text-lg"></i></button>
            </div>

            <div class="space-y-4 text-xs">
                <!-- Bank Status Alert Box -->
                <div id="withdrawBankAlert" class="bg-red-500/10 border border-red-500/20 p-3 rounded-xl flex items-center justify-between text-red-400">
                    <span><i class="fa-solid fa-triangle-exclamation mr-1"></i> Bank card not bound!</span>
                    <button onclick="closeModal('withdrawModal'); openBankModal();" class="bg-red-600 text-white px-2.5 py-1 rounded-lg text-[10px] font-bold">Add Bank</button>
                </div>

                <!-- Preset Amount Buttons -->
                <div class="space-y-1.5">
                    <label class="text-gray-400">Select Withdrawal Amount (Rs.)</label>
                    <div class="grid grid-cols-3 gap-2">
                        <button type="button" onclick="setWithdrawAmount(350)" class="withdraw-preset-btn bg-gray-900 hover:bg-gray-700 border border-gray-700 py-2.5 rounded-xl font-bold text-white transition">350</button>
                        <button type="button" onclick="setWithdrawAmount(800)" class="withdraw-preset-btn bg-gray-900 hover:bg-gray-700 border border-gray-700 py-2.5 rounded-xl font-bold text-white transition">800</button>
                        <button type="button" onclick="setWithdrawAmount(1700)" class="withdraw-preset-btn bg-gray-900 hover:bg-gray-700 border border-gray-700 py-2.5 rounded-xl font-bold text-white transition">1700</button>
                        <button type="button" onclick="setWithdrawAmount(3400)" class="withdraw-preset-btn bg-gray-900 hover:bg-gray-700 border border-gray-700 py-2.5 rounded-xl font-bold text-white transition">3400</button>
                        <button type="button" onclick="setWithdrawAmount(5500)" class="withdraw-preset-btn bg-gray-900 hover:bg-gray-700 border border-gray-700 py-2.5 rounded-xl font-bold text-white transition">5500</button>
                        <button type="button" onclick="setWithdrawAmount(14000)" class="withdraw-preset-btn bg-gray-900 hover:bg-gray-700 border border-gray-700 py-2.5 rounded-xl font-bold text-white transition">14000</button>
                    </div>
                </div>

                <form id="withdrawSubmitForm" onsubmit="submitWithdraw(event)" class="space-y-3">
                    <div>
                        <label class="text-gray-400">Amount (Strictly selected from above)</label>
                        <input type="number" id="withdrawAmountInput" required readonly placeholder="Select amount above" class="w-full mt-1 bg-gray-900/70 border border-gray-700 rounded-xl p-3 text-brand-400 font-bold outline-none cursor-not-allowed">
                    </div>
                    <div>
                        <label class="text-gray-400">Receive Amount After Deduction (20% Fee)</label>
                        <input type="text" id="withdrawReceiveInput" readonly placeholder="Rs. 0.00" class="w-full mt-1 bg-gray-900 border border-gray-700 rounded-xl p-3 text-emerald-400 font-bold outline-none">
                    </div>
                    
                    <!-- Note Section -->
                    <div class="bg-gray-900/80 border border-gray-700/60 p-3 rounded-xl space-y-1 text-[11px] text-gray-400">
                        <p class="font-bold text-gray-300"><i class="fa-solid fa-circle-info text-brand-400 mr-1"></i> Withdrawal Notice:</p>
                        <p>• Total processing fee: <strong>20%</strong></p>
                        <p>• Settlement time: <strong>24 Hours to 72 Hours</strong></p>
                        <p>• Allowed Hours: <strong>Mon - Sat (3:00 PM - 5:00 PM)</strong></p>
                    </div>

                    <button type="submit" id="withdrawSubmitBtn" class="w-full bg-brand-600 hover:bg-brand-700 text-white py-3 rounded-xl text-xs font-semibold transition shadow-lg shadow-brand-600/30">Submit Withdrawal</button>
                </form>
            </div>
        </div>
    </div>
<!-- Transfer Modal -->
    <div id="transferModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-gray-800 border border-gray-700 rounded-3xl p-6 w-full max-w-sm space-y-4 animate-fade-in shadow-2xl max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center border-b border-gray-700 pb-3">
                <h4 class="font-bold text-white text-base">Transfer Funds</h4>
                <button onclick="closeModal('transferModal')" class="text-gray-400 hover:text-white"><i class="fa-solid fa-xmark text-lg"></i></button>
            </div>

            <div class="space-y-4 text-xs">
                <!-- Preset Amount Buttons -->
                <div class="space-y-1.5">
                    <label class="text-gray-400">Select Transfer Amount (Rs.)</label>
                    <div class="grid grid-cols-3 gap-2">
                        <button type="button" onclick="setTransferAmount(200)" class="transfer-preset-btn bg-gray-900 hover:bg-gray-700 border border-gray-700 py-2.5 rounded-xl font-bold text-white transition">200</button>
                        <button type="button" onclick="setTransferAmount(500)" class="transfer-preset-btn bg-gray-900 hover:bg-gray-700 border border-gray-700 py-2.5 rounded-xl font-bold text-white transition">500</button>
                        <button type="button" onclick="setTransferAmount(800)" class="transfer-preset-btn bg-gray-900 hover:bg-gray-700 border border-gray-700 py-2.5 rounded-xl font-bold text-white transition">800</button>
                        <button type="button" onclick="setTransferAmount(1200)" class="transfer-preset-btn bg-gray-900 hover:bg-gray-700 border border-gray-700 py-2.5 rounded-xl font-bold text-white transition">1200</button>
                        <button type="button" onclick="setTransferAmount(1700)" class="transfer-preset-btn bg-gray-900 hover:bg-gray-700 border border-gray-700 py-2.5 rounded-xl font-bold text-white transition">1700</button>
                        <button type="button" onclick="setTransferAmount(2300)" class="transfer-preset-btn bg-gray-900 hover:bg-gray-700 border border-gray-700 py-2.5 rounded-xl font-bold text-white transition">2300</button>
                    </div>
                </div>

                <form id="transferSubmitForm" onsubmit="submitTransfer(event)" class="space-y-3">
                    <div>
                        <label class="text-gray-400">Amount (Strictly selected from above)</label>
                        <input type="number" id="transferAmountInput" required readonly placeholder="Select amount above" class="w-full mt-1 bg-gray-900/70 border border-gray-700 rounded-xl p-3 text-brand-400 font-bold outline-none cursor-not-allowed">
                    </div>
                    <div>
                        <label class="text-gray-400">Recipient Email</label>
                        <input type="email" id="transferEmailInput" required placeholder="Enter recipient email" class="w-full mt-1 bg-gray-900 border border-gray-700 rounded-xl p-3 text-white font-bold outline-none focus:border-brand-500">
                    </div>
                    <div>
                        <label class="text-gray-400">OTP Verification</label>
                        <div class="flex gap-2 mt-1">
                            <input type="text" id="transferOtpInput" required placeholder="Enter 6-digit OTP" class="w-full bg-gray-900 border border-gray-700 rounded-xl p-3 text-white font-bold outline-none focus:border-brand-500 tracking-widest">
                            <button type="button" onclick="sendTransferOtp()" id="sendOtpBtn" class="bg-gray-700 hover:bg-gray-600 text-white px-4 rounded-xl font-semibold transition shrink-0">Get OTP</button>
                        </div>
                    </div>
                    <div>
                        <label class="text-gray-400">Deducted Amount After Fee (10% Fee)</label>
                        <input type="text" id="transferReceiveInput" readonly placeholder="Rs. 0.00" class="w-full mt-1 bg-gray-900 border border-gray-700 rounded-xl p-3 text-emerald-400 font-bold outline-none">
                    </div>
                    
                    <!-- Note Section -->
                    <div class="bg-gray-900/80 border border-gray-700/60 p-3 rounded-xl space-y-1 text-[11px] text-gray-400">
                        <p class="font-bold text-gray-300"><i class="fa-solid fa-circle-info text-brand-400 mr-1"></i> Transfer Notice:</p>
                        <p>• Total processing fee: <strong>10%</strong></p>
                        <p>• Settlement Time: <strong>Instant</strong></p>
                        <p>• Allowed Hours: <strong>24/7</strong></p>
                        <p>• Limit: <strong>Once in a month for each account.</strong></p>
                    </div>

                    <button type="submit" id="transferSubmitBtn" disabled class="cursor-not-allowed w-full bg-brand-600/50  text-white py-3 rounded-xl text-xs font-semibold transition shadow-lg">Transfer</button>
                </form>
            </div>
        </div>
    </div>

                        
    <!-- 1. Account Details Modal -->
    <div id="accountDetailsModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-gray-800 border border-gray-700 rounded-3xl p-6 w-full max-w-sm space-y-4 animate-fade-in shadow-2xl">
            <div class="flex justify-between items-center border-b border-gray-700 pb-3">
                <h4 class="font-bold text-white text-base">Account Details</h4>
                <button onclick="closeModal('accountDetailsModal')" class="text-gray-400 hover:text-white"><i class="fa-solid fa-xmark text-lg"></i></button>
            </div>
            <div class="space-y-3 text-xs">
                <div class="bg-gray-900 p-3.5 rounded-2xl border border-gray-700/50 flex justify-between items-center">
                    <span class="text-gray-400">Full Name:</span>
                    <span class="font-semibold text-white"><?php echo $fullName; ?></span>
                </div>
                <div class="bg-gray-900 p-3.5 rounded-2xl border border-gray-700/50 flex justify-between items-center">
                    <span class="text-gray-400">Email Address:</span>
                    <span class="font-semibold text-white truncate max-w-[180px]"><?php echo htmlspecialchars($userEmail); ?></span>
                </div>
                <div class="bg-gray-900 p-3.5 rounded-2xl border border-gray-700/50 flex justify-between items-center">
                    <span class="text-gray-400">Sponsor / Inviter:</span>
                    <span class="font-semibold text-brand-400"><?php echo $sponsorName; ?></span>
                </div>
                <div class="bg-gray-900 p-3.5 rounded-2xl border border-gray-700/50 flex justify-between items-center">
                    <span class="text-gray-400">Referral Code:</span>
                    <span class="font-semibold text-white"><?php echo $referralCode; ?></span>
                </div>
            </div>
            <button onclick="closeModal('accountDetailsModal')" class="w-full bg-brand-600 hover:bg-brand-700 text-white py-3 rounded-xl text-xs font-semibold transition shadow-lg shadow-brand-600/30">Close</button>
        </div>
    </div>

    <!-- 2. Bind Bank Card Modal -->
    <div id="bankModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-gray-800 border border-gray-700 rounded-3xl p-6 w-full max-w-sm space-y-4 animate-fade-in shadow-2xl">
            <div class="flex justify-between items-center border-b border-gray-700 pb-3">
                <h4 class="font-bold text-white text-base">Bind Bank Card</h4>
                <button onclick="closeModal('bankModal')" class="text-gray-400 hover:text-white"><i class="fa-solid fa-xmark text-lg"></i></button>
            </div>
            <form id="bankForm" onsubmit="saveBankDetails(event)" class="space-y-3 text-xs">
                <div>
                    <label class="text-gray-400">Account Holder Name</label>
                    <input type="text" id="bankHolder" required class="w-full mt-1 bg-gray-900 border border-gray-700 rounded-xl p-3 text-white outline-none focus:border-brand-500">
                </div>
                <div>
                    <label class="text-gray-400">Bank Name</label>
                    <input type="text" id="bankName" required class="w-full mt-1 bg-gray-900 border border-gray-700 rounded-xl p-3 text-white outline-none focus:border-brand-500">
                </div>
                <div>
                    <label class="text-gray-400">Account Number</label>
                    <input type="text" id="bankAccNo" required class="w-full mt-1 bg-gray-900 border border-gray-700 rounded-xl p-3 text-white outline-none focus:border-brand-500">
                </div>
                <div>
                    <label class="text-gray-400">IFSC Code</label>
                    <input type="text" id="bankIfsc" required class="w-full mt-1 bg-gray-900 border border-gray-700 rounded-xl p-3 text-white outline-none focus:border-brand-500 uppercase">
                </div>
                <button type="submit" class="w-full bg-brand-600 hover:bg-brand-700 text-white py-3 rounded-xl text-xs font-semibold transition mt-2 shadow-lg shadow-brand-600/30">Save Bank Details</button>
            </form>
        </div>
    </div>

    <!-- 3. Team Report Modal -->
    <div id="teamReportModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-gray-800 border border-gray-700 rounded-3xl p-6 w-full max-w-md space-y-4 animate-fade-in max-h-[80vh] flex flex-col shadow-2xl">
            <div class="flex justify-between items-center border-b border-gray-700 pb-3">
    <h4 class="font-bold text-white text-base">Team Report</h4>
    <button onclick="closeModal('teamReportModal')" class="text-gray-400 hover:text-white"><i class="fa-solid fa-xmark text-lg"></i></button>
</div>
<!-- Level Selection Tabs -->
<div class="relative w-full max-w-xs">
    <select id="teamLevelSelect" onchange="switchTeamLevel(this.value)" class="w-full bg-gray-900 border border-gray-700 text-white text-xs font-semibold rounded-xl px-3 py-2.5 focus:outline-none focus:border-brand-500 transition">
        <option value="1" selected>Level 1</option>
        <option value="2">Level 2</option>
        <option value="3">Level 3</option>
        <option value="4">Level 4</option>
        <option value="5">Level 5</option>
        <option value="6">Level 6</option>
        <option value="7">Level 7</option>
        <option value="8">Level 8</option>
        <option value="9">Level 9</option>
        <option value="10">Level 10</option>
    </select>
</div>

<div id="teamListContainer" class="overflow-y-auto space-y-2 flex-1 pr-1 text-xs">
    <!-- Dynamically loaded team list -->
</div>

            <button onclick="closeModal('teamReportModal')" class="w-full bg-brand-600 hover:bg-brand-700 text-white py-3 rounded-xl text-xs font-semibold transition">Close</button>
        </div>
    </div>

    <!-- 4. Financial Records Modal -->
    <div id="financialModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-gray-800 border border-gray-700 rounded-3xl p-6 w-full max-w-md space-y-4 animate-fade-in max-h-[80vh] flex flex-col shadow-2xl">
            <div class="flex justify-between items-center border-b border-gray-700 pb-3">
                <h4 class="font-bold text-white text-base">Financial Records</h4>
                <button onclick="closeModal('financialModal')" class="text-gray-400 hover:text-white"><i class="fa-solid fa-xmark text-lg"></i></button>
            </div>
            <div id="financialListContainer" class="overflow-y-auto space-y-2.5 flex-1 pr-1 text-xs">
                <!-- Dynamically loaded financial/deposit records -->
            </div>
            <button onclick="closeModal('financialModal')" class="w-full bg-brand-600 hover:bg-brand-700 text-white py-3 rounded-xl text-xs font-semibold transition">Close</button>
        </div>
    </div>

    <!-- 5. Change Password Modal -->
    <div id="changePasswordModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-gray-800 border border-gray-700 rounded-3xl p-6 w-full max-w-sm space-y-4 animate-fade-in shadow-2xl">
            <div class="flex justify-between items-center border-b border-gray-700 pb-3">
                <h4 class="font-bold text-white text-base">Change Password</h4>
                <button onclick="closeModal('changePasswordModal')" class="text-gray-400 hover:text-white"><i class="fa-solid fa-xmark text-lg"></i></button>
            </div>
            <form id="passwordForm" onsubmit="changePassword(event)" class="space-y-3 text-xs">
                <div>
                    <label class="text-gray-400">New Password</label>
                    <input type="password" id="newPass" required class="w-full mt-1 bg-gray-900 border border-gray-700 rounded-xl p-3 text-white outline-none focus:border-brand-500">
                </div>
                <div>
                    <label class="text-gray-400">Confirm Password</label>
                    <input type="password" id="confirmPass" required class="w-full mt-1 bg-gray-900 border border-gray-700 rounded-xl p-3 text-white outline-none focus:border-brand-500">
                </div>
                <div>
                    <label class="text-gray-400">OTP Code</label>
                    <div class="flex gap-2 mt-1">
                        <input type="text" id="otpCode" required placeholder="Enter OTP" class="w-full bg-gray-900 border border-gray-700 rounded-xl p-3 text-white outline-none focus:border-brand-500">
                        <button type="button" onclick="sendOtp()" class="bg-gray-700 hover:bg-gray-600 text-white px-3 rounded-xl whitespace-nowrap font-medium transition">Get OTP</button>
                    </div>
                </div>
                <button type="submit" class="w-full bg-brand-600 hover:bg-brand-700 text-white py-3 rounded-xl text-xs font-semibold transition mt-2 shadow-lg shadow-brand-600/30">Update Password</button>
            </form>
        </div>
    </div>

    <!-- 6. Official Welcome Letter Modal -->
    <div id="welcomeLetterModal" class="fixed inset-0 bg-black/80 backdrop-blur-md z-50 hidden flex items-center justify-center p-4">
        <div id="printableLetterArea" class="bg-gray-900 border border-gray-700 rounded-3xl p-6 w-full max-w-lg space-y-5 animate-fade-in max-h-[90vh] overflow-y-auto shadow-2xl relative">
            <div class="flex justify-between items-center border-b border-gray-800 pb-3 print:hidden">
                <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-lg overflow-hidden flex items-center justify-center">
                       <img src="download.png" alt="FBAY Logo" class="w-full h-full object-cover">
                   </div>
                    <h4 class="font-bold text-white text-base">Official Welcome Letter</h4>
                </div>
                <button onclick="closeModal('welcomeLetterModal')" class="text-gray-400 hover:text-white"><i class="fa-solid fa-xmark text-lg"></i></button>
            </div>

            <!-- Letter Content Body -->
            <div class="space-y-4 text-xs text-gray-300 bg-gray-800/50 border border-gray-800 p-6 rounded-2xl">
                <div class="text-center pb-3 border-b border-gray-700/60 space-y-1">
                    <h2 class="text-lg font-extrabold text-white tracking-wider">FBAY GLOBAL ENTERPRISE</h2>
                    <p class="text-[11px] text-brand-400 font-medium">Official Member Accreditation & Welcome Certificate</p>
                    <p class="text-[10px] text-gray-400">Date: <?php echo $registrationDate; ?></p>               
                </div>

                <div class="space-y-3 leading-relaxed">
                    <p class="font-semibold text-white">Dear <?php echo $fullName; ?>,</p>
                    <p>
                        On behalf of the Board of Directors and the executive management team at <strong>Fbay Company</strong>, it is with great enthusiasm that we officially welcome you to our growing global network of trendsetters, retail partners, and digital entrepreneurs.
                    </p>
                    <p>
                        Your account has been successfully verified and securely registered within our secure infrastructure. Below are your official account particulars registered in our system:
                    </p>
                </div>

                <!-- Account Credentials Summary Box inside Letter -->
                <div class="bg-gray-900 border border-gray-700/60 rounded-xl p-3.5 space-y-2">
                    <div class="flex justify-between items-center border-b border-gray-800 pb-1.5">
                        <span class="text-gray-400">Member Name:</span>
                        <span class="font-bold text-white"><?php echo $fullName; ?></span>
                    </div>
                    <div class="flex justify-between items-center border-b border-gray-800 pb-1.5">
                        <span class="text-gray-400">Registered Email:</span>
                        <span class="font-bold text-white"><?php echo htmlspecialchars($userEmail); ?></span>
                    </div>
                    <div class="flex justify-between items-center border-b border-gray-800 pb-1.5">
                        <span class="text-gray-400">Referral Code:</span>
                        <span class="font-bold text-brand-400"><?php echo $referralCode; ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-400">Sponsor / Inviter:</span>
                        <span class="font-bold text-white"><?php echo $sponsorName; ?></span>
                    </div>
                </div>

                <div class="space-y-2 pt-1">
                    <p>
                        As an authorized Fbay member, you are granted full access to our premium clothing collection, cashback programs, and dynamic team referral rewards. We encourage you to review our guidelines and build your network responsibly.
                    </p>
                    <p class="text-[11px] text-gray-400 italic">
                        This is a computer-generated document and does not require a physical signature. Certified secure by Fbay Core Security.
                    </p>
                </div>

                <div class="pt-4 flex justify-between items-center border-t border-gray-700/60 text-[11px]">
                    <div>
                        <p class="font-bold text-white">Authorized Signatory</p>
                        <p class="text-gray-400">Fbay Management</p>
                    </div>
                    <div class="text-right w-16 h-12 flex items-center justify-center ml-auto">
                        <span class="text-brand-400 italic text-lg" style="font-family: 'Brush Script MT', cursive;">
                           Fbay
                       </span>
                    </div>
                </div>
            </div>

            <!-- Action Buttons for Letter Modal -->
            <div class="flex gap-3 pt-2 print:hidden">
                <button onclick="printWelcomeLetter()" class="flex-1 bg-brand-600 hover:bg-brand-700 text-white py-3 rounded-xl text-xs font-semibold transition flex items-center justify-center gap-2 shadow-lg shadow-brand-600/30">
                    <i class="fa-solid fa-print"></i> Print / Download PDF
                </button>
                <button onclick="closeModal('welcomeLetterModal')" class="bg-gray-800 hover:bg-gray-700 text-gray-300 px-5 py-3 rounded-xl text-xs font-semibold border border-gray-700 transition">
                    Close
                </button>
            </div>
        </div>
    </div>

    <!-- Confirm Buy Plan Modal -->
    <div id="buyPlanModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-gray-800 border border-gray-700 rounded-3xl p-6 w-full max-w-sm space-y-4 animate-fade-in shadow-2xl">
            <div class="flex justify-between items-center border-b border-gray-700 pb-3">
                <h4 class="font-bold text-white text-base">Confirm Investment</h4>
                <button onclick="closeModal('buyPlanModal')" class="text-gray-400 hover:text-white"><i class="fa-solid fa-xmark text-lg"></i></button>
            </div>
            <div class="space-y-3 text-xs text-gray-300">
                <p>You are about to purchase:</p>
                <div class="bg-gray-900 p-3.5 rounded-2xl border border-gray-700/60 space-y-2">
                    <div class="flex justify-between"><span class="text-gray-400">Plan Name:</span><span id="modalPlanName" class="font-bold text-white">---</span></div>
                    <div class="flex justify-between"><span class="text-gray-400">Amount:</span><span id="modalPlanAmount" class="font-bold text-brand-400">---</span></div>
                    <div class="flex justify-between"><span class="text-gray-400">Daily Reward:</span><span id="modalPlanReward" class="font-bold text-emerald-400">---</span></div>
                </div>
                <p class="text-[11px] text-gray-400">• Funds will be deducted directly from your account balance.</p>
            </div>
            <div class="flex gap-2 pt-2">
                <button onclick="closeModal('buyPlanModal')" class="w-1/2 bg-gray-700 hover:bg-gray-600 text-white py-2.5 rounded-xl font-semibold transition">Cancel</button>
                <button onclick="submitBuyPlan()" id="confirmBuyBtn" class="w-1/2 bg-brand-600 hover:bg-brand-700 text-white py-2.5 rounded-xl font-semibold transition shadow-lg shadow-brand-600/30">Confirm</button>
            </div>
        </div>
    </div>

    <!-- Task Active Overlay HTML -->
    <div id="taskOverlay" class="fixed inset-0 bg-black/80 z-50 hidden flex items-center justify-center p-4">
        <div class="bg-gray-900 border border-gray-800 w-full max-w-md rounded-2xl p-6 text-white relative shadow-2xl">
            <!-- Close Button -->
            <button onclick="closeTaskOverlay()" class="absolute top-4 right-4 text-gray-400 hover:text-white text-xl">
                <i class="fa-solid fa-xmark"></i>
            </button>

            <h3 class="text-lg font-bold text-center mb-1 text-emerald-400">Grab Order Task</h3>
            <p class="text-xs text-center text-gray-400 mb-6">Complete orders to earn commission</p>

            <!-- Order Container -->
            <div id="orderContent" class="space-y-4">
                <!-- Dynamic Product Info Insert Hoga Yahan -->
            </div>

            <!-- Progress Counter -->
            <div class="mt-6 text-center text-xs text-gray-400">
                Order <span id="currentOrderNum" class="text-white font-bold">0</span> of <span id="totalOrdersNum" class="text-white font-bold">0</span>
            </div>
        </div>
    </div>

    <!-- Sticky Telegram Floating Button (Added above Bottom Navigation) -->
    <a href="https://t.me/Fbay2016" target="_blank" rel="noopener noreferrer" class="fixed bottom-20 right-4 z-40 w-12 h-12 bg-sky-500 hover:bg-sky-600 text-white rounded-full flex items-center justify-center shadow-lg shadow-sky-500/40 transition-all duration-300 hover:scale-110 active:scale-95" title="Join Telegram Support">
        <i class="fa-brands fa-telegram text-2xl"></i>
    </a>

    <!-- Sticky 5-Icon Bottom Navigation -->
    <nav aria-label="Bottom Navigation" class="fixed bottom-0 left-0 right-0 max-w-md mx-auto bg-gray-800/90 backdrop-blur-xl border-t border-gray-700/60 px-4 py-2 z-50 rounded-t-3xl shadow-2xl">
        <ul class="flex justify-around items-center">
            <li>
                <button onclick="switchTab(this, 'Home')" class="nav-item flex flex-col items-center gap-1 text-brand-500 transition duration-200 py-1">
                    <i class="fa-solid fa-house text-lg"></i>
                    <span class="text-[10px] font-medium">Home</span>
                </button>
            </li>
            <li>
                <button onclick="switchTab(this, 'Plans')" class="nav-item flex flex-col items-center gap-1 text-gray-400 hover:text-gray-200 transition duration-200 py-1">
                    <i class="fa-solid fa-layer-group text-lg"></i>
                    <span class="text-[10px] font-medium">Plans</span>
                </button>
            </li>
            <li>
                <button onclick="switchTab(this, 'Task'); loadUserPlans();" class="nav-item flex flex-col items-center gap-1 text-gray-400 hover:text-gray-200 transition duration-200 py-1">
                    <i class="fa-solid fa-list-check text-lg"></i>
                    <span class="text-[10px] font-medium">Task</span>
                </button>
            </li>
            <li>
                <button onclick="switchTab(this, 'Invite')" class="nav-item flex flex-col items-center gap-1 text-gray-400 hover:text-gray-200 transition duration-200 py-1">
                    <i class="fa-solid fa-user-plus text-lg"></i>
                    <span class="text-[10px] font-medium">Invite</span>
                </button>
            </li>
            <li>
                <button onclick="switchTab(this, 'Setting')" class="nav-item flex flex-col items-center gap-1 text-gray-400 hover:text-gray-200 transition duration-200 py-1">
                    <i class="fa-solid fa-gear text-lg"></i>
                    <span class="text-[10px] font-medium">Setting</span>
                </button>
            </li>
        </ul>
    </nav>

    <script>
        let selectedPaymentChannel = '';
        let currentDailyReward = 0;
        let activeOrdersQueue = [];
        let currentOrderIndex = 0;

        const mockProducts = [
            { name: "Casual Cotton T-Shirt", price: 499, img: "https://images.unsplash.com/photo-1521572267360-ee0c2909d518?w=300" },
            { name: "Slim Fit Denim Jeans", price: 1299, img: "https://images.unsplash.com/photo-1542272604-787c3835535d?w=300" },
            { name: "Running Sports Shoes", price: 1999, img: "https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=300" },
            { name: "Smart Fitness Watch", price: 2499, img: "https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=300" },
            { name: "Leather Casual Wallet", price: 799, img: "https://images.unsplash.com/photo-1627123424574-724758594e93?w=300" },
            { name: "Classic Aviator Sunglasses", price: 999, img: "https://images.unsplash.com/photo-1572635196237-14b3f281503f?w=300" },
            { name: "Wireless Bluetooth Earbuds", price: 1499, img: "https://images.unsplash.com/photo-1590658268037-6bf12165a8df?w=300" },
            { name: "Waterproof Travel Backpack", price: 1799, img: "https://images.unsplash.com/photo-1553062407-98eeb64c6a62?w=300" }
        ];

        // Switch Reward and Salary Switcher Function
        function switchRewardSalary(type) {
            const rewardTab = document.getElementById('rewardTabBtn');
            const salaryTab = document.getElementById('salaryTabBtn');
            const rewardDiv = document.getElementById('rewardContent');
            const salaryDiv = document.getElementById('salaryContent');

            if (type === 'reward') {
                rewardTab.className = "flex-1 py-2 text-xs font-bold rounded-xl bg-brand-600 text-white transition shadow-md flex items-center justify-center gap-1.5";
                salaryTab.className = "flex-1 py-2 text-xs font-bold rounded-xl text-gray-400 hover:text-white transition flex items-center justify-center gap-1.5";
                rewardDiv.classList.remove('hidden');
                salaryDiv.classList.add('hidden');
            } else {
                salaryTab.className = "flex-1 py-2 text-xs font-bold rounded-xl bg-brand-600 text-white transition shadow-md flex items-center justify-center gap-1.5";
                rewardTab.className = "flex-1 py-2 text-xs font-bold rounded-xl text-gray-400 hover:text-white transition flex items-center justify-center gap-1.5";
                salaryDiv.classList.remove('hidden');
                rewardDiv.classList.add('hidden');
            }
        }

        // Custom Toast Function
        function showCustomToast(isSuccess, titleText, messageText) {
          Swal.fire({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 4500,
            didOpen: (popup) => {
              popup.style.display = 'inline-flex';
              popup.style.flexDirection = 'column';
              popup.style.alignItems = 'flex-start';
              popup.style.backgroundColor = isSuccess ? '#16a34a' : '#dc2626';
              popup.style.color = '#fff';
              popup.style.borderRadius = '8px';
              popup.style.padding = '10px 14px';
              popup.style.boxShadow = isSuccess
                ? '0 4px 12px rgba(22,163,74,0.4), 0 0 10px rgba(22,163,74,0.8)'
                : '0 4px 12px rgba(220,38,38,0.4), 0 0 10px rgba(220,38,38,0.8)';
              popup.style.border = isSuccess ? '1px solid #22c55e' : '1px solid #ef4444';
              popup.style.maxWidth = '90%';
              popup.style.width = 'auto';

              const titleContainer = document.createElement('div');
              titleContainer.style.display = 'flex';
              titleContainer.style.alignItems = 'center';
              titleContainer.style.gap = '8px';

              const icon = document.createElement('div');
              icon.innerHTML = isSuccess
                ? `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="white" viewBox="0 0 24 24"><path d="M9 16.2l-4.2-4.2L3.4 13.4 9 19l12-12-1.4-1.4z"/></svg>`
                : `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="white" viewBox="0 0 24 24"><path d="M18.3 5.71L12 12l6.3 6.29-1.42 1.42L12 13.41l-6.29 6.3-1.42-1.42L10.59 12 4.29 5.71 5.71 4.29 12 10.59l6.29-6.3z"/></svg>`;

              const title = document.createElement('div');
              title.textContent = titleText;
              title.style.fontWeight = 'bold';
              title.style.fontSize = '14px';

              const text = document.createElement('div');
              text.textContent = messageText;
              text.style.fontSize = '13px';
              text.style.paddingLeft = '26px';
              text.style.marginTop = '2px';

              titleContainer.appendChild(icon);
              titleContainer.appendChild(title);
              popup.appendChild(titleContainer);
              popup.appendChild(text);
            },
          });
        }

        // Modal Helpers
        function openModal(modalId) { document.getElementById(modalId).classList.remove('hidden'); }
        function closeModal(modalId) { document.getElementById(modalId).classList.add('hidden'); }

        window.addEventListener('DOMContentLoaded', () => {
            const refLink = encodeURIComponent("<?php echo $referralLink; ?>");
            const qrImgUrl = `https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=${refLink}&bgcolor=ffffff&color=000000`;
            document.getElementById('qrCodeImg').src = qrImgUrl;
            loadUserPlans();
        });

        function openDepositChannels() { openModal('depositChannelsModal'); }

        function selectChannel(channelName) {
    selectedPaymentChannel = channelName;
    closeModal('depositChannelsModal');

    // Define UPI mapping for channels
    let upiAddress = '';
  //  if (channelName === 'Channel 1') {
   //     upiAddress = "santoshkumari2027@axl";
   //  else
         if (channelName === 'Channel 2') {
        upiAddress = "ranaqamar15451-2@okhdfcbank";
    } else {
        showCustomToast(false, 'Under Maintenance', `Please use Channel 1 or Channel 2.`);
        return;
    }

    document.getElementById('depositModalTitle').innerText = `Deposit via ${channelName}`;
    document.getElementById('upiIdText').innerText = upiAddress;
    
    const upiString = `upi://pay?pa=${upiAddress}&pn=Fbay`;
    const qrImgUrl = `https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${encodeURIComponent(upiString)}&bgcolor=ffffff&color=000000`;
    document.getElementById('depositQrImg').src = qrImgUrl;

    document.getElementById('depositAmountInput').value = '';
    document.querySelectorAll('.preset-btn').forEach(btn => btn.classList.remove('border-brand-500', 'bg-brand-600/20'));
    openModal('depositFormModal');
}


        function setDepositAmount(amount) {
            document.getElementById('depositAmountInput').value = amount;
            document.querySelectorAll('.preset-btn').forEach(btn => {
                btn.classList.remove('border-brand-500', 'bg-brand-600/20');
                if(btn.innerText == amount) { btn.classList.add('border-brand-500', 'bg-brand-600/20'); }
            });
        }

        function copyUpi() {
            navigator.clipboard.writeText(document.getElementById('upiIdText').innerText).then(() => {
                showCustomToast(true, 'Copied', 'UPI ID copied to clipboard!');
            });
        }

        function submitDeposit(e) {
            e.preventDefault();
            const amount = document.getElementById('depositAmountInput').value;
            const utr = document.getElementById('depositUtrInput').value;

            if(!amount || !utr) { showCustomToast(false, 'Error', 'Please select an amount and fill in the UTR.'); return; }

            const formData = new URLSearchParams({ action: 'submit_deposit', channel: selectedPaymentChannel, amount: amount, utr: utr });

            fetch('auth.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    showCustomToast(true, 'Success', data.message || 'Deposit request submitted successfully!');
                    closeModal('depositFormModal');
                    document.getElementById('depositSubmitForm').reset();
                } else {
                    showCustomToast(false, 'Error', data.message || 'Failed to submit deposit.');
                }
            }).catch(() => showCustomToast(false, 'Error', 'Network error occurred.'));
        }

        function openWithdrawModal() {
            fetch('auth.php?action=get_bank')
            .then(res => res.json())
            .then(data => {
                const alertBox = document.getElementById('withdrawBankAlert');
                const submitBtn = document.getElementById('withdrawSubmitBtn');
                
                if(data.success && data.bank && data.bank.account_number) {
                    alertBox.classList.add('hidden');
                    submitBtn.disabled = false;
                    submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                } else {
                    alertBox.classList.remove('hidden');
                    submitBtn.disabled = true;
                    submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
                }
                
                document.getElementById('withdrawAmountInput').value = '';
                document.getElementById('withdrawReceiveInput').value = '';
                document.querySelectorAll('.withdraw-preset-btn').forEach(btn => btn.classList.remove('border-brand-500', 'bg-brand-600/20'));
                openModal('withdrawModal');
            }).catch(() => showCustomToast(false, 'Error', 'Failed to verify bank details.'));
        }

        function setWithdrawAmount(amount) {
            document.getElementById('withdrawAmountInput').value = amount;
            const netAmount = amount - (amount * 0.20);
            document.getElementById('withdrawReceiveInput').value = `Rs. ${netAmount.toFixed(2)}`;
            
            document.querySelectorAll('.withdraw-preset-btn').forEach(btn => {
                btn.classList.remove('border-brand-500', 'bg-brand-600/20');
                if(btn.innerText == amount) { btn.classList.add('border-brand-500', 'bg-brand-600/20'); }
            });
        }

        function submitWithdraw(e) {
            e.preventDefault();
            const amount = document.getElementById('withdrawAmountInput').value;
            if(!amount) { showCustomToast(false, 'Error', 'Please select a withdrawal amount.'); return; }

            const formData = new URLSearchParams({ action: 'submit_withdrawal', amount: amount });
            fetch('auth.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    showCustomToast(true, 'Success', data.message || 'Withdrawal request submitted successfully!');
                    closeModal('withdrawModal');
                    document.getElementById('withdrawSubmitForm').reset();
                } else {
                    showCustomToast(false, 'Error', data.message || 'Failed to submit withdrawal.');
                }
            }).catch(() => showCustomToast(false, 'Error', 'Network error occurred.'));
        }

        function openTransferModal() {
            document.getElementById('transferAmountInput').value = '';
            document.getElementById('transferEmailInput').value = '';
            document.getElementById('transferOtpInput').value = '';
            document.getElementById('transferReceiveInput').value = '';
            document.querySelectorAll('.transfer-preset-btn').forEach(btn => btn.classList.remove('border-brand-500', 'bg-brand-600/20'));
            
            // Reset OTP button state if needed
            const otpBtn = document.getElementById('sendOtpBtn');
            if(otpBtn) {
                otpBtn.disabled = false;
                otpBtn.innerText = 'Get OTP';
                otpBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            }
            
            openModal('transferModal');
        }

        function setTransferAmount(amount) {
            document.getElementById('transferAmountInput').value = amount;
            // 10% fee calculation: amount deducted means user sends full amount or net gets reduced? 
            // In the HTML note: "Total processing fee: 10%". Let's deduct 10% from the input amount.
            const netAmount = amount - (amount * 0.10);
            document.getElementById('transferReceiveInput').value = `Rs. ${netAmount.toFixed(2)}`;
            
            document.querySelectorAll('.transfer-preset-btn').forEach(btn => {
                btn.classList.remove('border-brand-500', 'bg-brand-600/20');
                if(btn.innerText == amount) { btn.classList.add('border-brand-500', 'bg-brand-600/20'); }
            });
        }
function sendTransferOtp() {
    const email = document.getElementById('transferEmailInput').value;
    const amount = document.getElementById('transferAmountInput').value;

    if(!email) {
        showCustomToast(false, 'Error', 'Please enter recipient email first.');
        return;
    }
    if(!amount) {
        showCustomToast(false, 'Error', 'Please select a transfer amount.');
        return;
    }

    const otpBtn = document.getElementById('sendOtpBtn');
    otpBtn.disabled = true;
    otpBtn.classList.add('opacity-50', 'cursor-not-allowed');

    fetch('auth.php', { 
        method: 'POST', 
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'send_transfer_otp', email: email, amount: amount }) 
    })
    .then(async res => {
        const rawText = await res.text();
        try {
            // Extra text ya concatenated JSON ko fix karne ke liye safe extraction
            const cleanText = rawText.substring(rawText.lastIndexOf('{"success"'));
            return JSON.parse(cleanText);
        } catch (e) {
            throw new Error(rawText.trim() || "Invalid JSON response from server");
        }
    })
    .then(data => {
        if(data.success) {
            showCustomToast(true, 'Success', data.message || 'OTP sent successfully!');
            
            const submitBtn = document.getElementById('transferSubmitBtn');
            submitBtn.disabled = false;
            submitBtn.className = "w-full bg-brand-600 hover:bg-brand-700 text-white py-3 rounded-xl text-xs font-semibold transition shadow-lg shadow-brand-600/30 cursor-pointer";

            let countdown = 60;
            otpBtn.innerText = `${countdown}s`;
            const timer = setInterval(() => {
                countdown--;
                otpBtn.innerText = `${countdown}s`;
                if(countdown <= 0) {
                    clearInterval(timer);
                    otpBtn.disabled = false;
                    otpBtn.innerText = 'Get OTP';
                    otpBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                }
            }, 1000);
        } else {
            showCustomToast(false, 'Error', data.message || 'Failed to send OTP.');
            otpBtn.disabled = false;
            otpBtn.classList.remove('opacity-50', 'cursor-not-allowed');
        }
    })
    .catch(err => {
        showCustomToast(false, 'Error Details', err.message);
        otpBtn.disabled = false;
        otpBtn.classList.remove('opacity-50', 'cursor-not-allowed');
    });
}
function submitTransfer(e) {
    e.preventDefault();
    const amount = document.getElementById('transferAmountInput').value;
    const email = document.getElementById('transferEmailInput').value;
    const otp = document.getElementById('transferOtpInput').value;

    if(!amount) { showCustomToast(false, 'Error', 'Please select a transfer amount.'); return; }
    if(!email) { showCustomToast(false, 'Error', 'Please enter recipient email.'); return; }
    if(!otp || otp.length < 6) { showCustomToast(false, 'Error', 'Please enter a valid 6-digit OTP code.'); return; }

    fetch('auth.php', { 
        method: 'POST', 
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
            action: 'submit_transfer', 
            amount: amount, 
            email: email, 
            otp: otp 
        }) 
    })
    .then(async res => {
        const rawText = await res.text();
        try {
            const cleanText = rawText.substring(rawText.lastIndexOf('{"success"'));
            return JSON.parse(cleanText);
        } catch (e) {
            throw new Error(rawText.trim() || "Invalid JSON response from server");
        }
    })
    .then(data => {
        if(data.success) {
            showCustomToast(true, 'Success', data.message || 'Transfer request submitted successfully!');
            closeModal('transferModal');
            document.getElementById('transferSubmitForm').reset();
        } else {
            showCustomToast(false, 'Error', data.message || 'Failed to submit transfer.');
        }
    })
    .catch(err => {
        showCustomToast(false, 'Error Details', err.message);
    });
}

   function toggleNotifications() { showCustomToast(true, 'Notifications', 'No new notifications at the moment.'); }

        function switchTab(buttonElement, tabName) {
            document.querySelectorAll('.nav-item').forEach(item => {
                item.classList.remove('text-brand-500');
                item.classList.add('text-gray-400');
            });
            buttonElement.classList.remove('text-gray-400');
            buttonElement.classList.add('text-brand-500');

            ['homeView', 'inviteView', 'plansView', 'taskView', 'settingView'].forEach(view => {
                document.getElementById(view).classList.add('hidden');
            });

            document.getElementById(tabName.toLowerCase() + 'View').classList.remove('hidden');
        }

        function copyCode() {
            navigator.clipboard.writeText(document.getElementById('refCodeText').innerText).then(() => {
                showCustomToast(true, 'Copied', 'Referral code copied to clipboard!');
            });
        }

        function copyLink() {
            const linkInput = document.getElementById('refLinkInput');
            linkInput.select();
            navigator.clipboard.writeText(linkInput.value).then(() => {
                showCustomToast(true, 'Copied', 'Invite link copied to clipboard!');
            });
        }

        function shareLink() {
    if (navigator.share) {
        navigator.share({
            title: 'Join Fbay',
            text: 'Join Fbay using my referral link! 🎉 Register now and get ₹50 bonus instantly! 💰',
            url: document.getElementById('refLinkInput').value
        }).catch(() => {});
    } else {
        copyLink();
    }
}

        function openBankModal() {
            fetch('auth.php?action=get_bank')
            .then(res => res.json())
            .then(data => {
                if(data.success && data.bank) {
                    document.getElementById('bankHolder').value = data.bank.holder_name || '';
                    document.getElementById('bankName').value = data.bank.bank_name || '';
                    document.getElementById('bankAccNo').value = data.bank.account_number || '';
                    document.getElementById('bankIfsc').value = data.bank.ifsc_code || '';
                }
                openModal('bankModal');
            }).catch(() => openModal('bankModal'));
        }

        function saveBankDetails(e) {
            e.preventDefault();
            const formData = new URLSearchParams({
                action: 'save_bank',
                holder_name: document.getElementById('bankHolder').value,
                bank_name: document.getElementById('bankName').value,
                account_number: document.getElementById('bankAccNo').value,
                ifsc_code: document.getElementById('bankIfsc').value
            });

            fetch('auth.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if(data.success) { showCustomToast(true, 'Success', 'Bank details saved successfully!'); closeModal('bankModal'); }
                else { showCustomToast(false, 'Error', data.message || 'Failed to save bank details.'); }
            }).catch(() => showCustomToast(false, 'Error', 'Network error occurred.'));
        }

        let currentTeamLevel = 1;

function openTeamReport() {
    switchTeamLevel(1);
    openModal('teamReportModal');
}

function switchTeamLevel(level) {
    currentTeamLevel = level;
    
    // Update tab button styles for up to 10 levels
    for(let i = 1; i <= 10; i++) {
        const btn = document.getElementById(`lvlBtn${i}`);
        if(btn) {
            if(i === level) {
                btn.className = "flex-1 py-1.5 rounded-lg text-xs font-semibold bg-brand-600 text-white transition";
            } else {
                btn.className = "flex-1 py-1.5 rounded-lg text-xs font-semibold text-gray-400 hover:text-white transition";
            }
        }
    }

    const container = document.getElementById('teamListContainer');
    container.innerHTML = `<p class="text-gray-400 text-center py-4">Loading Level ${level} members...</p>`;

    fetch(`auth.php?action=get_team&level=${level}`)
    .then(res => res.json())
    .then(data => {
        if(data.success && data.team && data.team.length > 0) {
            let html = '';
            data.team.forEach(member => {
                const isActive = member.plan_status === 'Active';
                
                // Active/Inactive dono ke liye row background standard black (bg-gray-900) rakha hai
                const rowStyle = 'bg-gray-900 border-gray-700/50';
                
                const beepColor = isActive ? 'bg-emerald-500 animate-pulse' : 'bg-red-500 animate-pulse';
                const statusTextColor = isActive ? 'text-emerald-400' : 'text-red-400';
                const statusText = isActive ? 'Active' : 'Inactive';

                html += `
                    <div class="${rowStyle} p-3 rounded-xl border flex justify-between items-center transition">
                        <div>
                            <p class="font-bold text-white text-xs">${member.full_name || 'User'}</p>
                            <p class="text-[10px] text-gray-400">${member.email}</p>
                        </div>
                        <div class="text-right space-y-1">
                            <span class="text-brand-400 font-semibold block text-xs">${member.referral_code}</span>
                            <div class="flex items-center justify-end gap-1.5">
                                <span class="w-2 h-2 rounded-full ${beepColor} inline-block"></span>
                                <span class="text-[10px] font-bold ${statusTextColor}">${statusText}</span>
                            </div>
                            <span class="text-[9px] text-gray-500 block">${member.created_at || ''}</span>
                        </div>
                    </div>
                `;
            });
            container.innerHTML = html;
        } else { 
            container.innerHTML = `<p class="text-gray-400 text-center py-4">No members found in Level ${level}.</p>`; 
        }
    }).catch(() => { 
        container.innerHTML = '<p class="text-red-400 text-center py-4">Failed to load team report.</p>'; 
    });
}

        function sendOtp() {
            fetch('auth.php?action=send_password_otp')
            .then(res => res.json())
            .then(data => {
                if(data.success) { showCustomToast(true, 'OTP Sent', 'Verification OTP sent to your email!'); }
                else { showCustomToast(false, 'Error', data.message || 'Failed to send OTP.'); }
            }).catch(() => showCustomToast(false, 'Error', 'Network error occurred.'));
        }

        function changePassword(e) {
            e.preventDefault();
            const newPass = document.getElementById('newPass').value;
            const confirmPass = document.getElementById('confirmPass').value;
            const otpCode = document.getElementById('otpCode').value;

            if(newPass !== confirmPass) { showCustomToast(false, 'Error', 'Passwords do not match.'); return; }

            const formData = new URLSearchParams({ action: 'change_password', new_password: newPass, otp: otpCode });
            fetch('auth.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if(data.success) { showCustomToast(true, 'Success', 'Password changed successfully!'); closeModal('changePasswordModal'); document.getElementById('passwordForm').reset(); }
                else { showCustomToast(false, 'Error', data.message || 'Failed to update password.'); }
            }).catch(() => showCustomToast(false, 'Error', 'Network error occurred.'));
        }

        function printWelcomeLetter() {
    window.print();
}
function openPanModal() {
    document.getElementById('kycNoticeOverlay').style.display = 'none';
    document.getElementById('panModal').classList.remove('hidden');
}

function closePanModal() {
    document.getElementById('panModal').classList.add('hidden');
}

function savePanDetails(e) {
    e.preventDefault();
    const panInput = document.getElementById('panNumberInput').value.trim().toUpperCase();
    
    // Standard Indian PAN Format: 5 uppercase letters, 4 digits, 1 uppercase letter
    const panRegex = /^[A-Z]{5}[0-9]{4}[A-Z]$/;
    
    if (!panRegex.test(panInput)) {
        showCustomToast(false, 'Invalid PAN', 'Please enter a valid PAN format (e.g., ABCDE1234F).');
        return;
    }

    const formData = new URLSearchParams({
        action: 'save_pan',
        pan: panInput
    });

    fetch('auth.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            showCustomToast(true, 'Success', 'PAN updated successfully!');
            closePanModal();
            setTimeout(() => location.reload(), 1500);
        } else {
            showCustomToast(false, 'Error', data.message || 'Failed to update PAN.');
        }
    }).catch(() => showCustomToast(false, 'Error', 'Network error occurred.'));
}

        // --- 10. GET FINANCIAL RECORDS ACTION ---
function openFinancialRecords() {
    const container = document.getElementById('financialListContainer');
    container.innerHTML = '<p class="text-gray-400 text-center py-4">Loading financial records...</p>';
    openModal('financialModal');

    fetch('auth.php?action=get_financial_records')
    .then(res => res.json())
    .then(data => {
        if(data.success && data.records && data.records.length > 0) {
            let html = '';
            data.records.forEach(record => {
                let statusColor = 'text-yellow-400 bg-yellow-500/10 border-yellow-500/20';
                
                // Prefix aur Amount Color setting (Sender ke liye Red aur '-', Receiver ke liye Green aur '+')
                let prefix = '+';
                let amountColor = 'text-emerald-400';

                if (record.type === 'withdrawal' || record.type === 'transfer_sent') {
                    prefix = '-';
                    amountColor = 'text-red-400';
                }

                const status = (record.status || 'Pending').toLowerCase();

                if (status === 'approved' || status === 'success') {
                    statusColor = 'text-emerald-400 bg-emerald-500/10 border-emerald-500/20';
                } else if (status === 'rejected') {
                    statusColor = 'text-red-400 bg-red-500/10 border-red-500/20';
                    amountColor = 'text-red-400';
                }

                // Title and sub-details selection based on record type
                let title = '';
                let subDetails = '';
                
                if (record.type === 'order') {
                    title = 'Revenue';
                } else if (record.type === 'transfer_sent') {
                    title = 'Transfer Sent';
                    subDetails = `<p class="text-[11px] text-gray-400 font-mono">To: ${record.recipient_email || 'N/A'}</p>`;
                } else if (record.type === 'transfer_received') {
                    title = 'Transfer Received';
                    subDetails = `<p class="text-[11px] text-gray-400 font-mono">From: ${record.sender_email || 'N/A'}</p>`;
                } else if (record.type === 'referral') {
                    title = 'Referral';
                    subDetails = `<p class="text-[11px] text-gray-400 font-mono">From: ${record.source_email || 'N/A'}</p>`;
                } else if (record.type === 'level') {
                    title = `Level ${record.level || 'N/A'}`;
                    subDetails = `<p class="text-[11px] text-gray-400 font-mono">From: ${record.source_email || 'N/A'}</p>`;
                } else {
                    title = record.type.charAt(0).toUpperCase() + record.type.slice(1);
                    if (record.type === 'deposit', 'withdrawal') {
                        subDetails = `<p class="text-[11px] text-gray-400 font-mono">UTR: ${record.utr || 'N/A'}</p>`;
                    }
                }

                // Status badge for deposit, withdrawal, transfers, referral, and level
                let statusBadge = '';
                if (['deposit', 'withdrawal', 'transfer_sent', 'transfer_received'].includes(record.type)) {
                    statusBadge = `<span class="text-[10px] font-semibold px-2 py-0.5 rounded-full border ${statusColor} uppercase inline-block">${record.status || 'Pending'}</span>`;
                }

                // Use net_amount for received transfers, regular amount for others
                let displayAmount = record.amount;
                if (record.type === 'transfer_received' && record.net_amount !== undefined) {
                    displayAmount = record.net_amount;
                }

                html += `
                    <div class="bg-gray-900 p-3.5 rounded-2xl border border-gray-700/50 flex justify-between items-center">
                        <div class="space-y-0.5">
                            <p class="font-bold text-white text-xs">${title}</p>
                            ${subDetails}
                            <p class="text-[10px] text-gray-500">${record.created_at || ''}</p>
                        </div>
                        <div class="text-right space-y-1">
                            <span class="${amountColor} font-bold text-xs block">${prefix}Rs. ${parseFloat(displayAmount).toFixed(2)}</span>
                            ${statusBadge}
                        </div>
                    </div>
                `;
            });
            container.innerHTML = html;
        } else { 
            container.innerHTML = '<p class="text-gray-400 text-center py-4">No financial records found yet.</p>'; 
        }
    }).catch(() => { 
        container.innerHTML = '<p class="text-red-400 text-center py-4">Failed to load financial records.</p>'; 
    });
}


        let selectedPlanData = {};
        function openBuyPlanModal(planName, amount, dailyReward) {
            selectedPlanData = { planName, amount, dailyReward };
            document.getElementById('modalPlanName').innerText = planName;
            document.getElementById('modalPlanAmount').innerText = `Rs. ${amount.toLocaleString()}`;
            document.getElementById('modalPlanReward').innerText = `Rs. ${dailyReward.toFixed(2)}`;
            openModal('buyPlanModal');
        }

        function submitBuyPlan() {
            const btn = document.getElementById('confirmBuyBtn');
            btn.disabled = true;
            btn.innerText = 'Processing...';

            const formData = new URLSearchParams({ action: 'buy_plan', plan_name: selectedPlanData.planName, amount: selectedPlanData.amount, daily_reward: selectedPlanData.dailyReward });

            fetch('auth.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                btn.disabled = false; btn.innerText = 'Confirm';
                if(data.success) {
                    showCustomToast(true, 'Success', data.message || 'Plan purchased successfully!');
                    closeModal('buyPlanModal');
                    setTimeout(() => window.location.reload(), 1200);
                } else { showCustomToast(false, 'Error', data.message || 'Failed to purchase plan.'); }
            }).catch(() => { btn.disabled = false; btn.innerText = 'Confirm'; showCustomToast(false, 'Error', 'Network error occurred.'); });
        }
     
function loadUserPlans() {

    fetch('auth.php?action=get_user_plans', {
        method: 'GET',
        credentials: 'same-origin'
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response failed');
        }
        return response.json();
    })
    .then(data => {

        if (!data.success) {
            console.error(data.message || 'Unable to load plans');
            return;
        }

        const activeContainer =
            document.getElementById('activePlansContainer');

        const completedTableBody =
            document.getElementById('completedPlansTableBody');

        if (!activeContainer || !completedTableBody) {
            console.error('Plan containers not found');
            return;
        }

        let activeHtml = '';
        let completedHtml = '';

        let hasActive = false;
        let hasCompleted = false;

        data.plans.forEach(plan => {

            const claimedDays = parseInt(plan.claimed_days || 0);

            const dailyReward =
                parseFloat(plan.daily_reward || 0);

            const amount =
                parseFloat(plan.amount || 0);

            const totalRevenue =
                (dailyReward * claimedDays).toFixed(2);

            const progressPercentage =
                Math.min(100, (claimedDays / 365) * 100);

            const planStatus =
                String(plan.status || '').toLowerCase();

            if (planStatus === 'active') {

                hasActive = true;

                if (typeof currentDailyReward !== 'undefined') {
                    currentDailyReward = dailyReward;
                }
                let buttonHtml = '';

                if (plan.can_claim) {

                    buttonHtml = `
                        <button
                            type="button"
                            onclick="openTaskOverlay()"
                            class="w-full bg-emerald-600 hover:bg-emerald-500 active:scale-[0.98]
                                   text-white font-semibold py-2.5 rounded-xl transition-all
                                   duration-200 text-xs flex items-center justify-center gap-2
                                   shadow-lg shadow-emerald-600/25">

                            <i class="fa-solid fa-play"></i>

                            <span>Active / Complete Tasks</span>

                        </button>
                    `;

                } else {

                    buttonHtml = `
                        <button
                            type="button"
                            disabled
                            class="w-full bg-gray-700/80 text-gray-400
                                   font-semibold py-2.5 rounded-xl text-xs
                                   flex items-center justify-center gap-2
                                   cursor-not-allowed opacity-80">

                            <i class="fa-solid fa-clock"></i>

                            <span>Already Claimed Today</span>

                        </button>
                    `;
                }

                activeHtml += `
                    <div class="bg-gray-800 border border-gray-700/80
                                rounded-3xl p-4 space-y-3 shadow-xl">

                        <!-- HEADER -->
                        <div class="flex items-center justify-between">

                            <div class="flex items-center gap-3">

                                <div class="w-10 h-10 rounded-2xl
                                            bg-emerald-500/10
                                            text-emerald-400
                                            flex items-center justify-center text-lg">

                                    <i class="fa-solid fa-chart-line"></i>

                                </div>

                                <div>

                                    <h4 class="text-sm font-bold text-white">
                                        ${plan.plan_name || 'Investment Plan'}
                                    </h4>

                                    <p class="text-[11px] text-gray-400">
                                        Invested: Rs. ${amount.toFixed(2)}
                                    </p>

                                </div>

                            </div>

                            <span class="bg-emerald-500/10
                                         text-emerald-400
                                         text-[10px] font-bold
                                         px-2.5 py-1 rounded-full uppercase">

                                Active

                            </span>

                        </div>


                        <!-- REWARD BOX -->
                        <div class="bg-gray-900 p-3 rounded-2xl
                                    border border-gray-700/60
                                    grid grid-cols-2 gap-2 text-xs">

                            <div>

                                <span class="text-gray-400 block text-[10px]">
                                    Daily Reward
                                </span>

                                <span class="font-bold text-emerald-400">
                                    +Rs. ${dailyReward.toFixed(2)}
                                </span>

                            </div>

                            <div>

                                <span class="text-gray-400 block text-[10px]">
                                    Total Earned
                                </span>

                                <span class="font-bold text-brand-400">
                                    Rs. ${totalRevenue}
                                </span>

                            </div>

                        </div>


                        <!-- PROGRESS -->
                        <div class="space-y-1">

                            <div class="flex justify-between
                                        text-[11px] text-gray-400">

                                <span>
                                    Claimed Days: ${claimedDays} Days
                                </span>

                                <span>
                                    ${progressPercentage.toFixed(0)}%
                                </span>

                            </div>

                            <div class="w-full bg-gray-700
                                        h-2 rounded-full overflow-hidden">

                                <div
                                    class="bg-emerald-500 h-full
                                           rounded-full transition-all
                                           duration-500"
                                    style="width:${progressPercentage}%">
                                </div>

                            </div>

                        </div>


                        <!-- ACTION BUTTON -->
                        ${buttonHtml}

                    </div>
                `;

            }
            else {

                hasCompleted = true;

                completedHtml += `
                    <tr class="border-b border-gray-700/50
                               text-gray-300">

                        <td class="py-3 font-medium text-white">
                            ${plan.plan_name || 'Plan'}
                        </td>

                        <td class="py-3 text-gray-400">
                            Rs. ${amount.toFixed(2)}
                        </td>

                        <td class="py-3 font-semibold text-emerald-400">
                            Rs. ${totalRevenue}
                        </td>

                        <td class="py-3 text-right
                                   text-[11px] text-gray-500">

                            ${plan.created_at || '-'}

                        </td>

                    </tr>
                `;
            }

        });

        if (!hasActive) {

            activeHtml = `
                <div class="bg-gray-800/50
                            border border-dashed border-gray-700
                            rounded-3xl p-6 text-center
                            text-gray-400 text-xs">

                    <i class="fa-solid fa-box-open
                              text-gray-500 text-xl mb-2"></i>

                    <p>No active investment plan found.</p>

                </div>
            `;
        }

        if (!hasCompleted) {

            completedHtml = `
                <tr>

                    <td colspan="4"
                        class="text-center py-6 text-gray-500 text-xs">

                        <i class="fa-solid fa-clock-rotate-left
                                  mb-2 text-gray-600 text-lg"></i>

                        <div>No completed plans yet.</div>

                    </td>

                </tr>
            `;
        }
        activeContainer.innerHTML = activeHtml;

        completedTableBody.innerHTML = completedHtml;

    })
    .catch(error => {

        console.error(
            'Error loading user plans:',
            error
        );

    });
}

let dailyClaimProcessing = false;

function finishAllTasksAndClaim() {

    if (dailyClaimProcessing) {
        return;
    }

    dailyClaimProcessing = true;

    fetch('auth.php?action=complete_daily_tasks', {
        method: 'POST',

        headers: {
            'Content-Type': 'application/json'
        },

        credentials: 'same-origin',

        body: JSON.stringify({})
    })

    .then(response => {

        if (!response.ok) {
            throw new Error('Server error');
        }

        return response.json();

    })

    .then(data => {

        if (data.success) {

            console.log(
                'Daily tasks completed successfully.'
            );

            loadUserPlans();

        } else {

            console.error(
                data.message || 'Unable to complete daily tasks.'
            );

        }

    })

    .catch(error => {

        console.error(
            'Error completing daily tasks:',
            error
        );

    })

    .finally(() => {

        dailyClaimProcessing = false;

    });
}

document.addEventListener('DOMContentLoaded', function () {

    loadUserPlans();

});
        function openTaskOverlay() {
            if (currentDailyReward <= 0) {
                showCustomToast(false, "Error", "No active plan reward found!");
                return;
            }

            const totalOrders = Math.floor(Math.random() * (15 - 5 + 1)) + 5; // e.g. 5 to 15 orders daily
            let weights = [], weightSum = 0;
            for (let i = 0; i < totalOrders; i++) {
                let w = Math.random() * 1 + 0.5;
                weights.push(w);
                weightSum += w;
            }

            activeOrdersQueue = [];
            let allocatedCommission = 0;

            for (let i = 0; i < totalOrders; i++) {
                const prod = mockProducts[Math.floor(Math.random() * mockProducts.length)];
                let commission = (i === totalOrders - 1) ? parseFloat((currentDailyReward - allocatedCommission).toFixed(2)) : parseFloat(((weights[i] / weightSum) * currentDailyReward).toFixed(2));
                allocatedCommission += commission;

                activeOrdersQueue.push({
                    product_name: prod.name,
                    product_price: prod.price,
                    product_img: prod.img,
                    commission: Math.max(0.01, commission)
                });
            }

            currentOrderIndex = 0;
            document.getElementById('totalOrdersNum').innerText = activeOrdersQueue.length;
            document.getElementById('taskOverlay').classList.remove('hidden');
            renderCurrentOrder();
        }

        function closeTaskOverlay() {
            document.getElementById('taskOverlay').classList.add('hidden');
            loadUserPlans();
        }

        function renderCurrentOrder() {
            if (currentOrderIndex >= activeOrdersQueue.length) {
                document.getElementById('orderContent').innerHTML = `
                    <div class="text-center py-8 space-y-3">
                        <div class="w-16 h-16 bg-emerald-500/20 text-emerald-400 rounded-full flex items-center justify-center mx-auto text-2xl">
                            <i class="fa-solid fa-check"></i>
                        </div>
                        <h4 class="text-lg font-bold text-white">Daily Target Completed!</h4>
                        <p class="text-xs text-gray-400">All orders have been successfully submitted and commission added.</p>
                        <button onclick="closeTaskOverlay()" class="w-full bg-emerald-600 hover:bg-emerald-500 text-white font-semibold py-2.5 rounded-xl transition">Done</button>
                    </div>
                `;
                showCustomToast(true, "Completed", "Daily tasks completed successfully!");
                return;
            }

            const order = activeOrdersQueue[currentOrderIndex];
            document.getElementById('currentOrderNum').innerText = currentOrderIndex + 1;

            document.getElementById('orderContent').innerHTML = `
                <div class="flex items-center gap-4 bg-gray-800/50 p-3 rounded-xl border border-gray-700/50">
                    <img src="${order.product_img}" alt="Product" class="w-20 h-20 object-cover rounded-lg border border-gray-700">
                    <div class="flex-1">
                        <h4 class="text-sm font-semibold text-white line-clamp-1">${order.product_name}</h4>
                        <div class="text-xs text-gray-400 mt-1">Price: <span class="text-white font-medium">₹${order.product_price}</span></div>
                        <div class="text-xs text-emerald-400 font-semibold mt-0.5">Commission: +₹${order.commission}</div>
                    </div>
                </div>
                <button onclick="submitCurrentOrder()" id="submitOrderBtn" class="w-full bg-emerald-600 hover:bg-emerald-500 text-white font-semibold py-3 rounded-xl transition shadow-lg shadow-emerald-600/25 flex items-center justify-center gap-2">
                    <span>Submit Order</span>
                </button>
            `;
        }

        function submitCurrentOrder() {

    const btn = document.getElementById('submitOrderBtn');

    if (!btn) return;

    btn.disabled = true;
    btn.innerText = "Processing...";

    const orderData = activeOrdersQueue[currentOrderIndex];

    fetch('auth.php?action=submit_order', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        credentials: 'same-origin',
        body: JSON.stringify(orderData)
    })

    .then(response => {

        if (!response.ok) {
            throw new Error('Server error');
        }

        return response.json();

    })

    .then(data => {

        if (data.success) {

            showCustomToast(
                true,
                "Success",
                `Order submitted! +₹${orderData.commission} added`
            );

            currentOrderIndex++;

            if (currentOrderIndex >= activeOrdersQueue.length) {
               finishAllTasksAndClaim();
            }
            renderCurrentOrder();

        } else {

            showCustomToast(
                false,
                "Error",
                data.message || "Something went wrong!"
            );

            btn.disabled = false;
            btn.innerText = "Submit Order";
        }

    })

    .catch(err => {

        console.error('Order submit error:', err);

        showCustomToast(
            false,
            "Error",
            "Network error. Please try again."
        );

        btn.disabled = false;
        btn.innerText = "Submit Order";

    });
}
        function loadInviteTeamSummary() {
    const tableBody = document.getElementById('inviteTeamTableBody');
    if (!tableBody) return;

    tableBody.innerHTML = `<tr><td colspan="4" class="text-center py-4 text-gray-400">Loading team summary...</td></tr>`;

    fetch('auth.php?action=get_team_summary')
    .then(res => res.text())
    .then(rawText => {
        let data = null;
        
        // Response me se success: true wala JSON object extract karne ke liye
        const jsonRegex = /\{[\s\S]*?\}/g;
        let match;
        while ((match = jsonRegex.exec(rawText)) !== null) {
            try {
                const parsed = JSON.parse(match[0]);
                if (parsed.success === true) {
                    data = parsed;
                    break;
                }
            } catch (e) {}
        }

        if(!data) {
            try {
                data = JSON.parse(rawText.trim());
            } catch (e) {}
        }

        if(data && data.success && data.summary && data.summary.length > 0) {
            let html = '';
            data.summary.forEach(row => {
                html += `
                    <tr class="hover:bg-gray-900/80 transition">
                        <td class="py-2.5 px-3 font-bold text-brand-400">Level ${row.level}</td>
                        <td class="py-2.5 px-3 font-semibold text-white">${row.total_members}</td>
                        <td class="py-2.5 px-3">
                            <span class="text-emerald-400 font-bold">${row.active_members} Active</span> / 
                            <span class="text-red-400">${row.inactive_members} Inactive</span>
                        </td>
                        <td class="py-2.5 px-3 font-mono font-semibold text-emerald-400">Rs. ${parseFloat(row.total_plan_amount || 0).toFixed(2)}</td>
                    </tr>
                `;
            });
            tableBody.innerHTML = html;
        } else {
            tableBody.innerHTML = `<tr><td colspan="4" class="text-center py-4 text-gray-400">No team data available.</td></tr>`;
        }
    }).catch(() => {
        tableBody.innerHTML = `<tr><td colspan="4" class="text-center py-4 text-red-400 text-[11px] font-mono">Failed to load summary.</td></tr>`;
    });
}

document.addEventListener('DOMContentLoaded', () => {
    loadInviteTeamSummary();
});

let salaryCacheData = null;

function loadSalaryConditions() {
    fetch('auth.php?action=get_salary_progress')
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            salaryCacheData = data;
            document.getElementById('userCurrentPlan').innerText = data.current_plan || 'None';

            // 1. Monthly Ranks Render
            let monthlyHtml = '';
            const monthlyTiers = [
                { name: 'Silver', desc: 'Team Starter', amount: '1,800', key: 'Silver' },
                { name: 'Gold', desc: 'Team Silver', amount: '4,000', key: 'Gold' },
                { name: 'Platinum', desc: 'Team Gold', amount: '9,000', key: 'Platinum' },
                { name: 'Diamond', desc: 'Team Platinum', amount: '14,000', key: 'Diamond' },
                { name: 'Elite Pro', desc: 'Team Diamond', amount: '22,000', key: 'Elite Pro' }
            ];

            monthlyTiers.forEach(tier => {
                const isUnlocked = data.unlocked_monthly && data.unlocked_monthly.includes(tier.key);
                const badgeStyle = isUnlocked ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-400' : 'bg-gray-900 border-gray-700/50 text-gray-400';
                const statusText = isUnlocked ? '<span class="text-emerald-400 font-bold text-[10px]">Unlocked</span>' : '<span class="text-gray-500 text-[10px]">Locked</span>';

                monthlyHtml += `
                    <div onclick="openSalaryModal('monthly', '${tier.key}')" class="${badgeStyle} p-3 rounded-xl border flex justify-between items-center cursor-pointer hover:border-brand-500/50 transition">
                        <div>
                            <p class="font-bold text-white text-xs">${tier.name} <span class="text-[10px] text-gray-400 font-normal">(${tier.desc})</span></p>
                        </div>
                        <div class="text-right space-y-0.5">
                            <span class="text-emerald-400 font-bold text-xs block">Rs. ${tier.amount} /mo</span>
                            ${statusText}
                        </div>
                    </div>
                `;
            });
            document.getElementById('monthlySalaryContainer').innerHTML = monthlyHtml;

            // 2. Weekly Ranks Render
            let weeklyHtml = '';
            const weeklyTiers = [
                { name: 'Starter Referral', amount: '500', key: 'Starter' },
                { name: 'Silver Referral', amount: '800', key: 'Silver' },
                { name: 'Gold Referral', amount: '1,400', key: 'Gold' },
                { name: 'Platinum Referral', amount: '2,000', key: 'Platinum' },
                { name: 'Diamond Referral', amount: '2,800', key: 'Diamond' },
                { name: 'Elite Pro Referral', amount: '5,000', key: 'Elite Pro' }
            ];

            weeklyTiers.forEach(tier => {
                const isEligible = data.current_plan && data.current_plan.toLowerCase() === tier.key.toLowerCase() && data.weekly_eligible;
                const badgeStyle = isEligible ? 'bg-brand-500/10 border-brand-500/30 text-brand-400' : 'bg-gray-900 border-gray-700/50 text-gray-400';
                const statusText = isEligible ? '<span class="text-brand-400 font-bold text-[10px]">Eligible</span>' : '<span class="text-gray-500 text-[10px]">Pending</span>';

                weeklyHtml += `
                    <div onclick="openSalaryModal('weekly', '${tier.key}')" class="${badgeStyle} p-3 rounded-xl border flex justify-between items-center cursor-pointer hover:border-brand-500/50 transition">
                        <div>
                            <p class="font-bold text-white text-xs">${tier.name}</p>
                        </div>
                        <div class="text-right space-y-0.5">
                            <span class="text-brand-400 font-bold text-xs block">Rs. ${tier.amount}</span>
                            ${statusText}
                        </div>
                    </div>
                `;
            });
            document.getElementById('weeklySalaryContainer').innerHTML = weeklyHtml;
        }
    }).catch(() => {
        document.getElementById('monthlySalaryContainer').innerHTML = `<p class="text-red-400 text-center text-xs">Failed to load progress.</p>`;
    });
}

function openSalaryModal(type, tierKey) {
    if (!salaryCacheData) return;
    const modal = document.getElementById('salaryModal');
    const title = document.getElementById('modalTitle');
    const body = document.getElementById('modalBody');
    modal.classList.remove('hidden');

    if (type === 'monthly') {
        title.innerText = `Monthly Salary: ${tierKey}`;
        const currentCount = salaryCacheData.team_count || 0;
        const targetCount = 18;
        const remainingCount = Math.max(0, targetCount - currentCount);
        const hasCorrectPlan = salaryCacheData.current_plan && salaryCacheData.current_plan.toLowerCase() === tierKey.toLowerCase();

        body.innerHTML = `
            <div class="bg-gray-800/80 p-3 rounded-xl space-y-2 border border-gray-700">
                <div class="flex justify-between"><span class="text-gray-400">Your Current Plan:</span> <span class="font-bold text-white">${salaryCacheData.current_plan}</span></div>
                <div class="flex justify-between"><span class="text-gray-400">Required Plan:</span> <span class="font-bold text-brand-400">${tierKey}</span></div>
                <div class="flex justify-between"><span class="text-gray-400">Team Members (3 Levels):</span> <span class="font-bold text-white">${currentCount} / ${targetCount}</span></div>
                <div class="flex justify-between"><span class="text-gray-400">Remaining Members:</span> <span class="font-bold text-red-400">${remainingCount} more needed</span></div>
            </div>
            <p class="text-[11px] text-gray-400 italic">Condition: Your own plan must be ${tierKey} and a total of 18 members must be active across up to 3 levels.</p>
        `;
    } else {
        title.innerText = `Weekly Salary: ${tierKey} Referral`;
        const monTueRefs = salaryCacheData.mon_tue_referrals || 0;
        const targetRefs = 2;
        const remainingRefs = Math.max(0, targetRefs - monTueRefs);
        const hasCorrectPlan = salaryCacheData.current_plan && salaryCacheData.current_plan.toLowerCase() === tierKey.toLowerCase();

        body.innerHTML = `
            <div class="bg-gray-800/80 p-3 rounded-xl space-y-2 border border-gray-700">
                <div class="flex justify-between"><span class="text-gray-400">Mon-Tue Referrals:</span> <span class="font-bold text-white">${monTueRefs} / ${targetRefs}</span></div>
                <div class="flex justify-between"><span class="text-gray-400">Remaining Referrals:</span> <span class="font-bold text-red-400">${remainingRefs} more needed</span></div>
                <div class="flex justify-between"><span class="text-gray-400">Plan Match (${tierKey}):</span> <span class="font-bold ${hasCorrectPlan ? 'text-emerald-400' : 'text-red-400'}">${hasCorrectPlan ? 'Matched' : 'Mismatch'}</span></div>
            </div>
            <p class="text-[11px] text-gray-400 italic">Condition: There must be 2 active referrals on the same plan on either Monday or Tuesday of the week. This resets as soon as the week ends.</p>
            `;
   }
}

function closeSalaryModal() {
    document.getElementById('salaryModal').classList.add('hidden');
}

document.addEventListener('DOMContentLoaded', () => {
    loadSalaryConditions();
});

    </script>
</body>
</html>
