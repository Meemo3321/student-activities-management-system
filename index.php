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

// === جلب الإحصائيات الحقيقية من قاعدة البيانات ===
$stats = ['total_events' => 0, 'total_students' => 0, 'total_registrations' => 0, 'total_organizers' => 0];
$upcoming_events = [];
try {
    $result = $pdo->query("
        SELECT 
            (SELECT COUNT(*) FROM events WHERE status = 'منشورة') as total_events,
            (SELECT COUNT(*) FROM users WHERE role = 'student') as total_students,
            (SELECT COUNT(*) FROM registrations) as total_registrations,
            (SELECT COUNT(*) FROM users WHERE role = 'organizer') as total_organizers
    ");
    if ($result) $stats = $result->fetch(PDO::FETCH_ASSOC) ?: $stats;

    // === جلب أحدث 6 فعاليات قادمة منشورة ===
    $result2 = $pdo->query("
        SELECT e.*, u.full_name as organizer_name,
               (SELECT COUNT(*) FROM registrations WHERE event_id = e.id) as registrations_count
        FROM events e
        JOIN users u ON e.organizer_id = u.id
        WHERE e.status = 'منشورة' AND e.start_datetime > NOW()
        ORDER BY e.start_datetime ASC
        LIMIT 6
    ");
    if ($result2) $upcoming_events = $result2->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) {
    // استمر بقيم افتراضية لعدم إيقاف الصفحة
    error_log('index.php DB error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>رِواق - منصة إدارة الأنشطة اللاصفية</title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;900&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Homepage Styles -->
    <link rel="stylesheet" href="assets/css/homepage.css?v=<?php echo time(); ?>">
</head>

<body>

    <!-- Navbar -->
    <nav class="navbar">
        <div class="container navbar-container">
            <a href="index.php" class="navbar-brand">
                <img src="assets/images/favicon.png" alt="رِواق" style="width: 55px; height: 55px; object-fit: contain;">
            </a>
            
            <!-- Hamburger Menu Button (Mobile Only) -->
            <button class="navbar-hamburger" id="navbarHamburger" aria-label="Menu">
                <span></span>
                <span></span>
                <span></span>
            </button>
            
            <ul class="navbar-links" id="navbarLinks">
                <li><a href="index.php" class="navbar-link active"><i class="fas fa-home"></i> الرئيسية</a></li>
                <li><a href="events.php" class="navbar-link"><i class="fas fa-calendar"></i> الفعاليات</a></li>
                <li><a href="about.php" class="navbar-link"><i class="fas fa-info-circle"></i> عن المنصة</a></li>
            </ul>
            
            
            <div class="navbar-auth">
                <?php if ($isLoggedIn && $user): ?>
                    <!-- Logged In User Dropdown -->
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
                    <!-- Guest Buttons -->
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

    <!-- Mobile Navbar Overlay -->
    <div class="navbar-overlay" id="navbarOverlay"></div>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <div class="hero-content">
                <h1>رِواق - منصة إدارة الأنشطة اللاصفية</h1>
                <p>الوجهة الأولى لطلاب وطالبات جامعة لتنظيم وتوثيق الأنشطة الجامعية بكل سهولة</p>
                <div class="hero-buttons">
                    <a href="auth/register.php" class="hero-btn">
                        <i class="fas fa-rocket"></i> ابدأ الآن
                    </a>
                    <a href="auth/login.php" class="hero-btn hero-btn-outline">
                        <i class="fas fa-sign-in-alt"></i> تسجيل الدخول
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats-section">
        <div class="container">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-value"><?php echo $stats['total_events']; ?></div>
                    <div class="stat-label">فعالية منشورة</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-value"><?php echo $stats['total_students']; ?></div>
                    <div class="stat-label">طالب مسجل</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <div class="stat-value"><?php echo $stats['total_registrations']; ?></div>
                    <div class="stat-label">تسجيل في الفعاليات</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <div class="stat-value"><?php echo $stats['total_organizers']; ?></div>
                    <div class="stat-label">منظم فعاليات</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Events Section -->
    <section class="events-section" id="events">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">الفعاليات القادمة</h2>
                <p class="section-subtitle">اكتشف أحدث الفعاليات المتاحة وسجل الآن</p>
            </div>

            <?php if (empty($upcoming_events)): ?>
                <div style="text-align: center; padding: 3rem; color: #94a3b8;">
                    <i class="fas fa-calendar-times" style="font-size: 3rem; margin-bottom: 1rem; display: block;"></i>
                    <p style="font-size: 1.1rem;">لا توجد فعاليات قادمة حالياً</p>
                </div>
            <?php else: ?>
            <div class="events-grid">
                <?php 
                $arabic_months = ['يناير','فبراير','مارس','أبريل','مايو','يونيو','يوليو','أغسطس','سبتمبر','أكتوبر','نوفمبر','ديسمبر'];
                foreach ($upcoming_events as $ev): 
                    $month_idx = (int)date('n', strtotime($ev['start_datetime'])) - 1;
                    $day = date('d', strtotime($ev['start_datetime']));
                    $month = $arabic_months[$month_idx];
                    $time = date('h:i A', strtotime($ev['start_datetime']));
                    $seats_left = $ev['capacity'] - $ev['registrations_count'];
                ?>
                <div class="event-card">
                    <?php if (!empty($ev['image_path'])): ?>
                        <img src="<?php echo htmlspecialchars($ev['image_path']); ?>" alt="<?php echo htmlspecialchars($ev['title']); ?>" class="event-image">
                    <?php else: ?>
                        <div class="event-image" style="height: 200px; background: linear-gradient(135deg, #667eea, #764ba2); display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-calendar-alt" style="font-size: 3rem; color: rgba(255,255,255,0.3);"></i>
                        </div>
                    <?php endif; ?>
                    <div class="event-content">
                        <span class="event-category"><?php echo htmlspecialchars($ev['category']); ?></span>
                        <h3 class="event-title"><?php echo htmlspecialchars($ev['title']); ?></h3>
                        <div class="event-meta">
                            <div class="event-meta-item">
                                <i class="fas fa-calendar"></i>
                                <span><?php echo $day . ' ' . $month; ?></span>
                            </div>
                            <div class="event-meta-item">
                                <i class="fas fa-clock"></i>
                                <span><?php echo $time; ?></span>
                            </div>
                            <div class="event-meta-item">
                                <i class="fas fa-map-marker-alt"></i>
                                <span><?php echo htmlspecialchars($ev['location']); ?></span>
                            </div>
                            <div class="event-meta-item">
                                <i class="fas fa-users"></i>
                                <span><?php echo $seats_left > 0 ? $seats_left . ' مقعد متاح' : 'مكتمل'; ?></span>
                            </div>
                        </div>
                        <a href="event-details.php?id=<?php echo $ev['id']; ?>" class="btn btn-primary btn-block">
                            <i class="fas fa-eye"></i> عرض التفاصيل
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <a href="events.php" class="btn btn-outline btn-lg view-all-btn">
                عرض جميع الفعاليات
                <i class="fas fa-arrow-left"></i>
            </a>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <div class="footer-logo">
                        <img src="assets/images/favicon.png" alt="رِواق" style="width: 50px; height: 50px; object-fit: contain;">
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
    // Mobile Navbar Toggle
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
        
        // User Dropdown Toggle
        const userDropdownToggle = document.getElementById('userDropdownToggle');
        
        if (userDropdownToggle) {
            const userDropdown = userDropdownToggle.closest('.user-dropdown');
            
            userDropdownToggle.addEventListener('click', function(e) {
                e.stopPropagation();
                userDropdown.classList.toggle('active');
            });
            
            // Close dropdown when clicking outside (but not on dropdown items)
            document.addEventListener('click', function(e) {
                // Check if click is on a dropdown item link - don't close if it is
                const isDropdownItem = e.target.closest('.user-dropdown-item');
                
                if (userDropdown && !userDropdown.contains(e.target) && !isDropdownItem) {
                    userDropdown.classList.remove('active');
                }
            });
        }
    });
    </script>

</body>
</html>
