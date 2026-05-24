<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

$isLoggedIn = isset($_SESSION['user_id']);
$user = null;
$pdo = getDBConnection();

if ($isLoggedIn) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
}

// === جلب الفئات المتاحة من قاعدة البيانات ===
$categories = $pdo->query("
    SELECT DISTINCT category FROM events WHERE status = 'منشورة' ORDER BY category
")->fetchAll(PDO::FETCH_COLUMN);

// === فلترة حسب الفئة ===
$selected_category = $_GET['category'] ?? 'all';

// === جلب الفعاليات المنشورة ===
if ($selected_category !== 'all') {
    $stmt = $pdo->prepare("
        SELECT e.*, u.full_name as organizer_name,
               (SELECT COUNT(*) FROM registrations WHERE event_id = e.id AND status != 'ملغي') as registrations_count
        FROM events e
        JOIN users u ON e.organizer_id = u.id
        WHERE e.status = 'منشورة' AND e.category = ?
        ORDER BY e.start_datetime ASC
    ");
    $stmt->execute([$selected_category]);
} else {
    $stmt = $pdo->query("
        SELECT e.*, u.full_name as organizer_name,
               (SELECT COUNT(*) FROM registrations WHERE event_id = e.id AND status != 'ملغي') as registrations_count
        FROM events e
        JOIN users u ON e.organizer_id = u.id
        WHERE e.status = 'منشورة'
        ORDER BY e.start_datetime ASC
    ");
}
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

$arabic_months = ['يناير','فبراير','مارس','أبريل','مايو','يونيو','يوليو','أغسطس','سبتمبر','أكتوبر','نوفمبر','ديسمبر'];

// أيقونات الفئات
$category_icons = [
    'تقنية' => 'fas fa-laptop-code',
    'رياضة' => 'fas fa-futbol',
    'ثقافية' => 'fas fa-palette',
    'مؤتمر' => 'fas fa-users',
    'ورشة عمل' => 'fas fa-tools',
    'تطوعية' => 'fas fa-hands-helping',
    'اجتماعية' => 'fas fa-handshake',
];
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الفعاليات - رِواق</title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;900&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Homepage Styles -->
    <link rel="stylesheet" href="assets/css/homepage.css?v=<?php echo time(); ?>">
    
    <style>
        /* === Events Page Styles === */
        .page-hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 6rem 0 3rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .page-hero::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 500px;
            height: 500px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
        }
        .page-hero::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -10%;
            width: 400px;
            height: 400px;
            background: rgba(255,255,255,0.03);
            border-radius: 50%;
        }
        .page-hero h1 {
            font-size: 3rem;
            font-weight: 900;
            color: white;
            margin-bottom: 0.75rem;
            position: relative;
            z-index: 1;
        }
        .page-hero p {
            font-size: 1.15rem;
            color: rgba(255,255,255,0.85);
            max-width: 600px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }
        
        /* Filter Tabs */
        .filter-section {
            padding: 2rem 0 0;
            background: #f8fafc;
        }
        .filter-tabs {
            display: flex;
            gap: 0.6rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        .filter-tab {
            padding: 0.6rem 1.25rem;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 700;
            border: 2px solid #e2e8f0;
            background: white;
            color: #64748b;
            cursor: pointer;
            font-family: 'Cairo', sans-serif;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }
        .filter-tab:hover {
            border-color: #667eea;
            color: #667eea;
        }
        .filter-tab.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-color: transparent;
            box-shadow: 0 4px 15px rgba(102,126,234,0.35);
        }
        .filter-tab.active:hover {
            color: white;
        }
        
        /* Events Section */
        .public-events-section {
            padding: 2rem 0 4rem;
            background: #f8fafc;
        }
        .events-count {
            text-align: center;
            color: #94a3b8;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 2rem;
        }
        .events-count strong { color: #667eea; }
        
        /* Event Cards Grid */
        .public-events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 1.75rem;
        }
        .pub-event-card {
            border-radius: 20px;
            overflow: hidden;
            background: white;
            border: 1px solid rgba(0,0,0,0.06);
            transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 2px 12px rgba(0,0,0,0.04);
            display: flex;
            flex-direction: column;
        }
        .pub-event-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 20px 40px rgba(118,75,162,0.15);
        }
        .pub-card-img {
            height: 220px;
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .pub-card-img img {
            width: 100%; height: 100%; object-fit: cover;
            transition: transform 0.5s;
        }
        .pub-event-card:hover .pub-card-img img {
            transform: scale(1.08);
        }
        .pub-card-img .no-image-icon {
            position: absolute; inset: 0;
            display: flex; align-items: center; justify-content: center;
        }
        .pub-card-img .no-image-icon i {
            font-size: 3.5rem; color: rgba(255,255,255,0.2);
        }
        .pub-card-category {
            position: absolute; top: 1rem; right: 1rem;
            padding: 0.35rem 0.85rem; border-radius: 50px;
            background: rgba(255,255,255,0.95); color: #764ba2;
            font-size: 0.75rem; font-weight: 700; z-index: 2;
        }
        .pub-card-status {
            position: absolute; top: 1rem; left: 1rem;
            display: flex; gap: 0.4rem; z-index: 2;
        }
        .pub-card-badge {
            padding: 0.3rem 0.7rem; border-radius: 50px;
            font-size: 0.72rem; font-weight: 700;
            backdrop-filter: blur(12px);
        }
        .badge-past { background: rgba(100,116,139,0.9); color: white; }
        .badge-upcoming { background: rgba(16,185,129,0.9); color: white; }
        .badge-full { background: rgba(239,68,68,0.9); color: white; }
        
        .pub-date-chip {
            position: absolute; bottom: 1rem; right: 1rem; z-index: 2;
            background: white; border-radius: 14px; padding: 0.5rem 0.75rem;
            display: flex; align-items: center; gap: 0.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .pub-date-day { font-size: 1.4rem; font-weight: 900; color: #764ba2; line-height: 1; }
        .pub-date-info { display: flex; flex-direction: column; line-height: 1.2; }
        .pub-date-month { font-size: 0.7rem; font-weight: 700; color: #64748b; text-transform: uppercase; }
        .pub-date-time { font-size: 0.7rem; color: #94a3b8; font-weight: 600; }
        
        .pub-card-body { padding: 1.25rem; flex: 1; display: flex; flex-direction: column; }
        .pub-card-title {
            font-size: 1.15rem; font-weight: 800; color: #0f172a;
            margin-bottom: 0.5rem; line-height: 1.5;
        }
        .pub-card-desc {
            color: #64748b; font-size: 0.88rem; line-height: 1.7;
            margin-bottom: 1rem; flex: 1;
        }
        .pub-card-meta {
            display: flex; flex-wrap: wrap; gap: 0.6rem; margin-bottom: 1rem;
        }
        .pub-meta-item {
            display: flex; align-items: center; gap: 0.35rem;
            font-size: 0.82rem; color: #64748b;
            background: #f8fafc; padding: 0.3rem 0.65rem;
            border-radius: 8px; font-weight: 600;
        }
        .pub-meta-item i { font-size: 0.72rem; }
        
        .pub-seats-bar { margin-bottom: 1.25rem; }
        .pub-seats-header { display: flex; justify-content: space-between; margin-bottom: 0.4rem; }
        .pub-seats-label { font-size: 0.8rem; color: #94a3b8; font-weight: 600; }
        .pub-seats-count { font-size: 0.8rem; font-weight: 800; color: #764ba2; }
        .pub-seats-track { height: 6px; background: #f1f5f9; border-radius: 50px; overflow: hidden; }
        .pub-seats-fill {
            height: 100%; border-radius: 50px; transition: width 0.8s;
            background: linear-gradient(90deg, #667eea, #764ba2);
        }
        .pub-seats-fill.almost-full { background: linear-gradient(90deg, #f59e0b, #ef4444); }
        .pub-seats-fill.full { background: #ef4444; }
        
        .pub-card-btn {
            display: flex; align-items: center; justify-content: center; gap: 0.4rem;
            width: 100%; padding: 0.75rem; border-radius: 12px; font-weight: 700;
            font-size: 0.9rem; border: none; cursor: pointer; text-align: center;
            text-decoration: none; font-family: 'Cairo', sans-serif; transition: all 0.25s;
            background: linear-gradient(135deg, #667eea, #764ba2); color: white;
        }
        .pub-card-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(118,75,162,0.35);
            color: white;
        }
        
        .empty-state {
            text-align: center; padding: 4rem 2rem; color: #94a3b8;
        }
        .empty-state i { font-size: 4rem; margin-bottom: 1.5rem; display: block; opacity: 0.5; }
        .empty-state p { font-size: 1.15rem; font-weight: 600; }
        
        @media (max-width: 768px) {
            .public-events-grid { grid-template-columns: 1fr; }
            .page-hero h1 { font-size: 2rem; }
            .page-hero { padding: 4rem 0 2rem; }
        }
    </style>
</head>

<body>

    <!-- Navbar -->
    <nav class="navbar">
        <div class="container navbar-container">
            <a href="index.php" class="navbar-brand">
                <img src="assets/images/favicon.png" alt="رِواق" style="width: 55px; height: 55px; object-fit: contain;">
            </a>
            
            <button class="navbar-hamburger" id="navbarHamburger" aria-label="Menu">
                <span></span><span></span><span></span>
            </button>
            
            <ul class="navbar-links" id="navbarLinks">
                <li><a href="index.php" class="navbar-link"><i class="fas fa-home"></i> الرئيسية</a></li>
                <li><a href="events.php" class="navbar-link active"><i class="fas fa-calendar"></i> الفعاليات</a></li>
                <li><a href="about.php" class="navbar-link"><i class="fas fa-info-circle"></i> عن المنصة</a></li>
            </ul>
            
            <div class="navbar-auth">
                <?php if ($isLoggedIn && $user): ?>
                    <div class="user-dropdown">
                        <button class="user-dropdown-toggle" id="userDropdownToggle">
                            <img src="uploads/profiles/<?php echo $user['profile_image'] ?? 'default.jpg'; ?>" 
                                 alt="<?php echo htmlspecialchars($user['full_name']); ?>"
                                 class="user-dropdown-avatar"
                                 onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($user['full_name']); ?>&background=667eea&color=fff&size=64'">
                            <span class="user-dropdown-name"><?php echo htmlspecialchars(explode(' ', $user['full_name'])[0]); ?></span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="user-dropdown-menu" id="userDropdownMenu">
                            <?php 
                                if ($user['role'] === 'admin') {
                                    $dashboardLink = 'admin/dashboard.php';
                                } elseif ($user['role'] === 'coordinator') {
                                    $dashboardLink = 'coordinator/dashboard.php';
                                } elseif ($user['role'] === 'organizer') {
                                    $dashboardLink = 'organizer/dashboard.php';
                                } else {
                                    $dashboardLink = 'student/dashboard.php';
                                }
                            ?>
                            <a href="<?php echo $dashboardLink; ?>" class="user-dropdown-item">
                                <i class="fas fa-th-large"></i>
                                لوحة التحكم
                            </a>
                            <a href="auth/logout.php" class="user-dropdown-item">
                                <i class="fas fa-sign-out-alt"></i>
                                تسجيل الخروج
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="auth/login.php" class="btn btn-outline">
                        <i class="fas fa-sign-in-alt"></i>
                        <span>دخول</span>
                    </a>
                    <a href="auth/register.php" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i>
                        <span>تسجيل</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="navbar-overlay" id="navbarOverlay"></div>

    <!-- Page Hero -->
    <section class="page-hero">
        <div class="container">
            <h1><i class="fas fa-calendar-alt" style="margin-left: 0.5rem;"></i> الفعاليات والأنشطة</h1>
            <p>تصفح جميع الفعاليات المتاحة واشترك فيما يناسبك</p>
        </div>
    </section>

    <!-- Filter Section -->
    <section class="filter-section">
        <div class="container">
            <div class="filter-tabs">
                <a href="events.php" class="filter-tab <?php echo $selected_category === 'all' ? 'active' : ''; ?>">
                    <i class="fas fa-th"></i> الكل
                </a>
                <?php foreach ($categories as $cat): ?>
                    <a href="events.php?category=<?php echo urlencode($cat); ?>" 
                       class="filter-tab <?php echo $selected_category === $cat ? 'active' : ''; ?>">
                        <i class="<?php echo $category_icons[$cat] ?? 'fas fa-tag'; ?>"></i>
                        <?php echo htmlspecialchars($cat); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Events Section -->
    <section class="public-events-section">
        <div class="container">
            <div class="events-count">
                عرض <strong><?php echo count($events); ?></strong> فعالية
                <?php if ($selected_category !== 'all'): ?>
                    في فئة <strong><?php echo htmlspecialchars($selected_category); ?></strong>
                <?php endif; ?>
            </div>

            <?php if (empty($events)): ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-times"></i>
                    <p>لا توجد فعاليات <?php echo $selected_category !== 'all' ? 'في هذه الفئة' : 'حالياً'; ?></p>
                </div>
            <?php else: ?>
            <div class="public-events-grid">
                <?php foreach ($events as $ev): 
                    $is_past = strtotime($ev['start_datetime']) < time();
                    // capacity = NULL أو 0 تعني غير محدود — لا تُعتبر مكتملة أبداً
                    $has_limit  = !empty($ev['capacity']) && intval($ev['capacity']) > 0;
                    $is_full    = $has_limit && ($ev['registrations_count'] >= intval($ev['capacity']));
                    $seat_percent = $has_limit ? round(($ev['registrations_count'] / intval($ev['capacity'])) * 100) : 0;
                    $month_idx = (int)date('n', strtotime($ev['start_datetime'])) - 1;
                    $day = date('d', strtotime($ev['start_datetime']));
                    $month = $arabic_months[$month_idx];
                    $time = date('h:i A', strtotime($ev['start_datetime']));
                    $seats_left = $has_limit ? (intval($ev['capacity']) - $ev['registrations_count']) : null;
                ?>
                <div class="pub-event-card">
                    <div class="pub-card-img">
                        <?php if (!empty($ev['image_path'])): ?>
                            <img src="<?php echo htmlspecialchars($ev['image_path']); ?>" alt="<?php echo htmlspecialchars($ev['title']); ?>">
                        <?php else: ?>
                            <div class="no-image-icon"><i class="fas fa-calendar-alt"></i></div>
                        <?php endif; ?>

                        <span class="pub-card-category">
                            <i class="<?php echo $category_icons[$ev['category']] ?? 'fas fa-tag'; ?>"></i>
                            <?php echo htmlspecialchars($ev['category']); ?>
                        </span>

                        <div class="pub-card-status">
                            <?php if ($is_past): ?>
                                <span class="pub-card-badge badge-past"><i class="fas fa-history"></i> انتهت</span>
                            <?php elseif ($is_full): ?>
                                <span class="pub-card-badge badge-full"><i class="fas fa-lock"></i> مكتمل</span>
                            <?php else: ?>
                                <span class="pub-card-badge badge-upcoming"><i class="fas fa-bolt"></i> متاحة</span>
                            <?php endif; ?>
                        </div>

                        <div class="pub-date-chip">
                            <div class="pub-date-day"><?php echo $day; ?></div>
                            <div class="pub-date-info">
                                <span class="pub-date-month"><?php echo $month; ?></span>
                                <span class="pub-date-time"><?php echo $time; ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="pub-card-body">
                        <h3 class="pub-card-title"><?php echo htmlspecialchars($ev['title']); ?></h3>
                        <p class="pub-card-desc">
                            <?php echo htmlspecialchars(mb_substr($ev['description'], 0, 100)) . (mb_strlen($ev['description']) > 100 ? '...' : ''); ?>
                        </p>

                        <div class="pub-card-meta">
                            <span class="pub-meta-item"><i class="fas fa-map-marker-alt" style="color:#ef4444;"></i> <?php echo htmlspecialchars($ev['location']); ?></span>
                            <span class="pub-meta-item"><i class="fas fa-user-tie" style="color:#764ba2;"></i> <?php echo htmlspecialchars($ev['organizer_name']); ?></span>
                        </div>

                        <div class="pub-seats-bar">
                            <div class="pub-seats-header">
                                <span class="pub-seats-label">المقاعد</span>
                                <?php if ($has_limit): ?>
                                    <span class="pub-seats-count"><?php echo $ev['registrations_count']; ?> / <?php echo intval($ev['capacity']); ?></span>
                                <?php else: ?>
                                    <span class="pub-seats-count" style="color:#10b981;"><i class="fas fa-infinity"></i> غير محدود</span>
                                <?php endif; ?>
                            </div>
                            <div class="pub-seats-track">
                                <?php if ($has_limit): ?>
                                    <div class="pub-seats-fill <?php echo $seat_percent >= 100 ? 'full' : ($seat_percent >= 80 ? 'almost-full' : ''); ?>" 
                                         style="width: <?php echo min($seat_percent, 100); ?>%;"></div>
                                <?php else: ?>
                                    <div class="pub-seats-fill" style="width: <?php echo min(($ev['registrations_count'] > 0 ? 30 : 0), 100); ?>%; opacity:0.4;"></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <a href="event-details.php?id=<?php echo $ev['id']; ?>" class="pub-card-btn">
                            <i class="fas fa-eye"></i> عرض التفاصيل
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <div class="footer-logo">
                        <img src="assets/images/favicon.png" alt="رِواق" style="width: 55px; height: 55px; object-fit: contain;">
                    </div>
                    <p>منصة متكاملة لإدارة وتوثيق الأنشطة اللاصفية والفعاليات الطلابية</p>
                </div>

                <div class="footer-section">
                    <h3 class="footer-title">روابط سريعة</h3>
                    <ul class="footer-links">
                        <li><a href="index.php"><i class="fas fa-home"></i> الرئيسية</a></li>
                        <li><a href="events.php"><i class="fas fa-calendar"></i> الفعاليات</a></li>
                        <li><a href="about.php"><i class="fas fa-info"></i> عن المنصة</a></li>
                    </ul>
                </div>

                <div class="footer-section">
                    <h3 class="footer-title">تواصل معنا</h3>
                    <ul class="footer-links">
                        <li><i class="fas fa-envelope"></i> info@tarak.edu</li>
                        <li><i class="fas fa-phone"></i> +966 50 123 4567</li>
                        <li><i class="fas fa-map-marker-alt"></i> الجوف، المملكة العربية السعودية</li>
                    </ul>
                </div>
            </div>

            <div class="footer-bottom">
                <p>&copy; 2026 رِواق. جميع الحقوق محفوظة.</p>
            </div>
        </div>
    </footer>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const hamburger = document.getElementById('navbarHamburger');
        const navbarLinks = document.getElementById('navbarLinks');
        const overlay = document.getElementById('navbarOverlay');
        const navbarAuth = document.querySelector('.navbar-auth');
        
        if (hamburger) {
            hamburger.addEventListener('click', function() {
                hamburger.classList.toggle('active');
                navbarLinks.classList.toggle('active');
                overlay.classList.toggle('active');
                if (navbarAuth) navbarAuth.classList.toggle('active');
            });
        }
        
        if (overlay) {
            overlay.addEventListener('click', function() {
                hamburger.classList.remove('active');
                navbarLinks.classList.remove('active');
                overlay.classList.remove('active');
                if (navbarAuth) navbarAuth.classList.remove('active');
            });
        }
        
        // User Dropdown
        const userDropdownToggle = document.getElementById('userDropdownToggle');
        if (userDropdownToggle) {
            const userDropdown = userDropdownToggle.closest('.user-dropdown');
            userDropdownToggle.addEventListener('click', function(e) {
                e.stopPropagation();
                userDropdown.classList.toggle('active');
            });
            document.addEventListener('click', function(e) {
                if (userDropdown && !userDropdown.contains(e.target)) {
                    userDropdown.classList.remove('active');
                }
            });
        }
    });
    </script>

</body>
</html>
