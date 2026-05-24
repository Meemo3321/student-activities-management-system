<?php
session_start();
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

$isLoggedIn = isset($_SESSION['user_id']);
$user = null;

if ($isLoggedIn) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>عن المنصة - رِواق</title>
    
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
                <li><a href="index.php" class="navbar-link"><i class="fas fa-home"></i> الرئيسية</a></li>
                <li><a href="events.php" class="navbar-link"><i class="fas fa-calendar"></i> الفعاليات</a></li>
                <li><a href="about.php" class="navbar-link active"><i class="fas fa-info-circle"></i> عن المنصة</a></li>
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
                            <a href="<?php echo SITE_URL; ?>/auth/logout.php" class="user-dropdown-item">
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

    <!-- About Section -->
    <section style="padding: 5rem 0; background: white;">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">عن منصة رِواق</h2>
                <p class="section-subtitle">منصة متكاملة لإدارة الأنشطة اللاصفية والفعاليات الطلابية</p>
            </div>

            <div style="max-width: 900px; margin: 0 auto;">
                <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 3rem; border-radius: 1.5rem; color: white; margin-bottom: 3rem; text-align: center;">
                    <h3 style="font-size: 2rem; margin-bottom: 1rem; color: white;">رؤيتنا</h3>
                    <p style="font-size: 1.25rem; line-height: 1.8; color: rgba(255,255,255,0.95); margin: 0;">
                        أن نكون المنصة الأولى في المملكة العربية السعودية لإدارة وتوثيق الأنشطة اللاصفية، ونساهم في تطوير مهارات الطلاب وإثراء تجربتهم الجامعية
                    </p>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem; margin-bottom: 3rem;">
                    <div style="background: #f8fafc; padding: 2rem; border-radius: 1rem; border-right: 4px solid #667eea;">
                        <div style="width: 60px; height: 60px; background: linear-gradient(135deg, #667eea, #764ba2); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 1.5rem; color: white; font-size: 1.75rem;">
                            <i class="fas fa-bullseye"></i>
                        </div>
                        <h3 style="font-size: 1.5rem; margin-bottom: 1rem; color: #1e293b;">رسالتنا</h3>
                        <p style="color: #64748b; line-height: 1.8;">
                            توفير منصة رقمية متطورة تسهل على الطلاب المشاركة في الأنشطة اللاصفية، وتساعد المنظمين على إدارة الفعاليات بكفاءة عالية
                        </p>
                    </div>

                    <div style="background: #f8fafc; padding: 2rem; border-radius: 1rem; border-right: 4px solid #764ba2;">
                        <div style="width: 60px; height: 60px; background: linear-gradient(135deg, #667eea, #764ba2); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 1.5rem; color: white; font-size: 1.75rem;">
                            <i class="fas fa-star"></i>
                        </div>
                        <h3 style="font-size: 1.5rem; margin-bottom: 1rem; color: #1e293b;">قيمنا</h3>
                        <p style="color: #64748b; line-height: 1.8;">
                            الابتكار، الشفافية، التميز في الخدمة، التطوير المستمر، ودعم نمو الطلاب الشخصي والمهني
                        </p>
                    </div>
                </div>

                <div style="background: white; padding: 2.5rem; border-radius: 1.5rem; box-shadow: 0 10px 30px rgba(0,0,0,0.08); border: 1px solid #f1f5f9;">
                    <h3 style="font-size: 1.75rem; margin-bottom: 2rem; color: #1e293b; text-align: center;">
                        <i class="fas fa-rocket" style="color: #667eea; margin-left: 0.5rem;"></i>
                        ماذا نقدم؟
                    </h3>
                    <div style="display: grid; gap: 1.5rem;">
                        <div style="display: flex; align-items: start; gap: 1rem;">
                            <div style="width: 40px; height: 40px; background: #e0e7ff; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                <i class="fas fa-check" style="color: #667eea;"></i>
                            </div>
                            <div>
                                <h4 style="font-size: 1.125rem; margin-bottom: 0.5rem; color: #1e293b;">إدارة الفعاليات</h4>
                                <p style="color: #64748b; margin: 0;">نظام متكامل لإنشاء وإدارة الفعاليات بسهولة وكفاءة</p>
                            </div>
                        </div>

                        <div style="display: flex; align-items: start; gap: 1rem;">
                            <div style="width: 40px; height: 40px; background: #e0e7ff; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                <i class="fas fa-check" style="color: #667eea;"></i>
                            </div>
                            <div>
                                <h4 style="font-size: 1.125rem; margin-bottom: 0.5rem; color: #1e293b;">تسجيل الحضور</h4>
                                <p style="color: #64748b; margin: 0;">نظام ذكي لتسجيل حضور الطلاب باستخدام QR Code</p>
                            </div>
                        </div>

                        <div style="display: flex; align-items: start; gap: 1rem;">
                            <div style="width: 40px; height: 40px; background: #e0e7ff; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                <i class="fas fa-check" style="color: #667eea;"></i>
                            </div>
                            <div>
                                <h4 style="font-size: 1.125rem; margin-bottom: 0.5rem; color: #1e293b;">الشهادات الرقمية</h4>
                                <p style="color: #64748b; margin: 0;">إصدار شهادات مشاركة رقمية احترافية تلقائياً</p>
                            </div>
                        </div>

                        <div style="display: flex; align-items: start; gap: 1rem;">
                            <div style="width: 40px; height: 40px; background: #e0e7ff; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                <i class="fas fa-check" style="color: #667eea;"></i>
                            </div>
                            <div>
                                <h4 style="font-size: 1.125rem; margin-bottom: 0.5rem; color: #1e293b;">نظام النقاط</h4>
                                <p style="color: #64748b; margin: 0;">تتبع نقاط الطلاب وتحفيزهم على المشاركة الفعّالة</p>
                            </div>
                        </div>

                        <div style="display: flex; align-items: start; gap: 1rem;">
                            <div style="width: 40px; height: 40px; background: #e0e7ff; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                <i class="fas fa-check" style="color: #667eea;"></i>
                            </div>
                            <div>
                                <h4 style="font-size: 1.125rem; margin-bottom: 0.5rem; color: #1e293b;">التقارير والإحصائيات</h4>
                                <p style="color: #64748b; margin: 0;">تقارير تفصيلية ورؤى تحليلية لتحسين الأداء</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
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
        const userDropdown = userDropdownToggle?.closest('.user-dropdown');
        
        if (userDropdownToggle) {
            userDropdownToggle.addEventListener('click', function(e) {
                e.stopPropagation();
                userDropdown.classList.toggle('active');
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!userDropdown.contains(e.target)) {
                    userDropdown.classList.remove('active');
                }
            });
        }
    });
    </script>

</body>
</html>
