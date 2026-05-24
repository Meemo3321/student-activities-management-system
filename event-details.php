<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$pdo = getDBConnection();
$event_id = $_GET['id'] ?? 0;

$isLoggedIn = isset($_SESSION['user_id']);
$user = null;
$is_registered = false;
$registration_status = null;
$success_message = '';
$error_message = '';

if ($isLoggedIn) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
}

// جلب تفاصيل الفعالية
$stmt = $pdo->prepare("
    SELECT e.*, u.full_name as organizer_name, u.email as organizer_email,
           (SELECT COUNT(*) FROM registrations WHERE event_id = e.id AND status != 'ملغي') as registrations_count,
           (e.capacity - (SELECT COUNT(*) FROM registrations WHERE event_id = e.id AND status != 'ملغي')) as available_seats
    FROM events e
    JOIN users u ON e.organizer_id = u.id
    WHERE e.id = ?
");
$stmt->execute([$event_id]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

// هل السعة محدودة أم غير محدودة
$has_limit = !empty($event['capacity']) && intval($event['capacity']) > 0;

if (!$event) {
    header('Location: index.php');
    exit();
}

// التحقق من تسجيل المستخدم
if ($isLoggedIn && $user) {
    $reg_stmt = $pdo->prepare("SELECT status FROM registrations WHERE event_id = ? AND user_id = ?");
    $reg_stmt->execute([$event_id, $user['id']]);
    $reg = $reg_stmt->fetch(PDO::FETCH_ASSOC);
    if ($reg) {
        $is_registered = true;
        $registration_status = $reg['status'];
    }
}

// معالجة التسجيل في الفعالية
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_event']) && $isLoggedIn && $user) {
    if ($user['role'] !== 'student') {
        $error_message = 'التسجيل متاح للطلاب فقط.';
    } elseif ($is_registered) {
        $error_message = 'أنت مسجل مسبقاً في هذه الفعالية!';
    } elseif ($has_limit && $event['available_seats'] <= 0) {
        $error_message = 'عذراً، الفعالية ممتلئة!';
    } elseif (strtotime($event['start_datetime']) <= time()) {
        $error_message = 'عذراً، لا يمكن التسجيل في فعالية بدأت أو انتهت!';
    } else {
        try {
            $register_stmt = $pdo->prepare("
                INSERT INTO registrations (user_id, event_id, status, registered_at)
                VALUES (?, ?, 'مسجل', NOW())
            ");
            $register_stmt->execute([$user['id'], $event_id]);
            
            $success_message = 'تم تسجيلك بنجاح! في انتظار موافقة المنسق.';
            $is_registered = true;
            $registration_status = 'مسجل';
            $event['registrations_count']++;
            if ($has_limit) $event['available_seats']--;
        } catch (PDOException $e) {
            $error_message = 'حدث خطأ أثناء التسجيل. حاول مرة أخرى.';
        }
    }
}

// حساب المدة
$start = new DateTime($event['start_datetime']);
$end = new DateTime($event['end_datetime']);
$duration = $start->diff($end);

$arabic_months = ['يناير','فبراير','مارس','أبريل','مايو','يونيو','يوليو','أغسطس','سبتمبر','أكتوبر','نوفمبر','ديسمبر'];
$month_idx = (int)date('n', strtotime($event['start_datetime'])) - 1;
$day = date('d', strtotime($event['start_datetime']));
$month = $arabic_months[$month_idx];
$time_start = date('h:i A', strtotime($event['start_datetime']));

$seat_percent = $has_limit ? round(($event['registrations_count'] / intval($event['capacity'])) * 100) : 0;

$page_title = $event['title'] . ' - رِواق';
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;900&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Homepage Styles -->
    <link rel="stylesheet" href="assets/css/homepage.css?v=<?php echo time(); ?>">
    
    <style>
        /* === Event Details Page Styles === */
        .event-hero {
            position: relative;
            height: 400px;
            overflow: hidden;
            display: flex;
            align-items: flex-end;
        }
        .event-hero-bg {
            position: absolute; inset: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .event-hero-bg img {
            width: 100%; height: 100%; object-fit: cover; opacity: 0.35;
        }
        .event-hero-overlay {
            position: absolute; inset: 0;
            background: linear-gradient(to top, rgba(15,23,42,0.95) 0%, rgba(15,23,42,0.3) 100%);
        }
        .event-hero-content {
            position: relative; z-index: 2;
            padding: 2.5rem 0;
            width: 100%;
        }
        .event-hero-content .container {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            flex-wrap: wrap;
            gap: 1.5rem;
        }
        .event-hero-info { flex: 1; min-width: 280px; }
        .event-hero-badges {
            display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 1rem;
        }
        .hero-badge {
            padding: 0.4rem 1rem; border-radius: 50px;
            font-size: 0.8rem; font-weight: 700;
            backdrop-filter: blur(10px);
        }
        .hero-badge-category { background: rgba(255,255,255,0.2); color: white; }
        .hero-badge-registered { background: rgba(16,185,129,0.9); color: white; }
        .hero-badge-full { background: rgba(239,68,68,0.9); color: white; }
        .event-hero-title {
            font-size: 2.5rem; font-weight: 900; color: white;
            line-height: 1.4; margin-bottom: 0.75rem;
        }
        .event-hero-meta {
            display: flex; flex-wrap: wrap; gap: 1.25rem;
            color: rgba(255,255,255,0.8); font-size: 0.95rem;
        }
        .event-hero-meta i { margin-left: 0.4rem; color: var(--primary); }

        /* Main Content */
        .event-detail-content {
            padding: 3rem 0 4rem;
            background: #f8fafc;
        }
        .event-detail-grid {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 2rem;
            align-items: start;
        }
        .detail-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 2px 12px rgba(0,0,0,0.04);
            border: 1px solid rgba(0,0,0,0.06);
            margin-bottom: 1.5rem;
        }
        .detail-card-title {
            font-size: 1.2rem; font-weight: 700; color: #0f172a;
            margin-bottom: 1.5rem;
            display: flex; align-items: center; gap: 0.6rem;
        }
        .detail-card-title i {
            width: 36px; height: 36px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 10px; display: flex; align-items: center; justify-content: center;
            color: white; font-size: 0.9rem;
        }

        /* Description */
        .event-description {
            line-height: 2; color: #475569; font-size: 1rem;
            white-space: pre-wrap;
        }

        /* Info Items */
        .info-list { display: flex; flex-direction: column; gap: 1.25rem; }
        .info-item {
            display: flex; gap: 1rem; align-items: flex-start;
        }
        .info-icon {
            width: 48px; height: 48px; border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0; font-size: 1.1rem; color: white;
        }
        .info-item-text small { color: #94a3b8; font-size: 0.8rem; display: block; margin-bottom: 0.15rem; }
        .info-item-text strong { color: #0f172a; font-weight: 700; font-size: 0.95rem; }

        /* Capacity */
        .capacity-grid {
            display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem;
        }
        .capacity-box {
            text-align: center; padding: 1.25rem;
            background: #f8fafc; border-radius: 14px;
        }
        .capacity-value { font-size: 2rem; font-weight: 900; line-height: 1; margin-bottom: 0.25rem; }
        .capacity-label { font-size: 0.8rem; color: #94a3b8; font-weight: 600; }

        .seats-track { height: 8px; background: #f1f5f9; border-radius: 50px; overflow: hidden; margin-bottom: 1rem; }
        .seats-fill { height: 100%; border-radius: 50px; background: linear-gradient(90deg, #667eea, #764ba2); transition: width 0.8s; }
        .seats-fill.almost-full { background: linear-gradient(90deg, #f59e0b, #ef4444); }
        .seats-fill.full { background: #ef4444; }

        /* Register Button */
        .register-box { margin-top: 1.5rem; }
        .btn-register-event {
            width: 100%; padding: 1rem; border: none; border-radius: 14px;
            font-size: 1.1rem; font-weight: 800; cursor: pointer;
            display: flex; align-items: center; justify-content: center; gap: 0.5rem;
            font-family: 'Cairo', sans-serif; transition: all 0.3s;
        }
        .btn-register-event.btn-go {
            background: linear-gradient(135deg, #667eea, #764ba2); color: white;
            box-shadow: 0 6px 20px rgba(118,75,162,0.3);
        }
        .btn-register-event.btn-go:hover {
            transform: translateY(-3px); box-shadow: 0 10px 30px rgba(118,75,162,0.4);
        }
        .btn-register-event.btn-done {
            background: #059669; color: white; cursor: default;
        }
        .btn-register-event.btn-full {
            background: #e2e8f0; color: #94a3b8; cursor: not-allowed;
        }
        .btn-login-to-register {
            width: 100%; padding: 1rem; border: 2px solid var(--primary);
            border-radius: 14px; background: transparent; color: var(--primary);
            font-size: 1.05rem; font-weight: 700; cursor: pointer;
            font-family: 'Cairo', sans-serif; text-decoration: none;
            display: flex; align-items: center; justify-content: center; gap: 0.5rem;
            transition: all 0.3s;
        }
        .btn-login-to-register:hover {
            background: var(--primary); color: white;
        }

        /* Alert messages */
        .alert-msg {
            padding: 1rem 1.25rem; border-radius: 14px;
            margin-bottom: 1.5rem; font-size: 0.95rem; font-weight: 600;
            display: flex; align-items: center; gap: 0.6rem;
        }
        .alert-success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }

        /* Warning box */
        .seats-warning {
            padding: 0.75rem 1rem; border-radius: 12px;
            font-size: 0.85rem; font-weight: 600;
            display: flex; align-items: center; gap: 0.5rem;
        }
        .seats-warning.low { background: #fffbeb; color: #92400e; border-right: 4px solid #f59e0b; }
        .seats-warning.empty { background: #fef2f2; color: #991b1b; border-right: 4px solid #ef4444; }

        .back-link {
            display: inline-flex; align-items: center; gap: 0.5rem;
            color: #64748b; font-weight: 600; text-decoration: none;
            margin-bottom: 1.5rem; font-size: 0.95rem;
            transition: color 0.3s;
        }
        .back-link:hover { color: var(--primary); }

        @media (max-width: 900px) {
            .event-detail-grid { grid-template-columns: 1fr; }
            .event-hero { height: 320px; }
            .event-hero-title { font-size: 1.75rem; }
        }
        @media (max-width: 480px) {
            .event-hero { height: 260px; }
            .event-hero-title { font-size: 1.4rem; }
            .event-hero-meta { font-size: 0.85rem; }
            .detail-card { padding: 1.25rem; border-radius: 16px; }
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
                <li><a href="events.php" class="navbar-link"><i class="fas fa-calendar"></i> الفعاليات</a></li>
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

    <!-- Event Hero -->
    <section class="event-hero">
        <div class="event-hero-bg">
            <?php if (!empty($event['image_path'])): ?>
                <img src="<?php echo htmlspecialchars($event['image_path']); ?>" alt="<?php echo htmlspecialchars($event['title']); ?>">
            <?php endif; ?>
        </div>
        <div class="event-hero-overlay"></div>
        <div class="event-hero-content">
            <div class="container">
                <div class="event-hero-info">
                    <div class="event-hero-badges">
                        <span class="hero-badge hero-badge-category">
                            <i class="fas fa-tag"></i> <?php echo htmlspecialchars($event['category']); ?>
                        </span>
                        <?php if ($is_registered): ?>
                            <span class="hero-badge hero-badge-registered">
                                <i class="fas fa-check-circle"></i> مسجل
                            </span>
                        <?php endif; ?>
                        <?php if ($has_limit && $event['available_seats'] <= 0): ?>
                            <span class="hero-badge hero-badge-full">
                                <i class="fas fa-lock"></i> مكتمل
                            </span>
                        <?php endif; ?>
                    </div>
                    <h1 class="event-hero-title"><?php echo htmlspecialchars($event['title']); ?></h1>
                    <div class="event-hero-meta">
                        <span><i class="fas fa-calendar-alt"></i> <?php echo $day . ' ' . $month; ?></span>
                        <span><i class="fas fa-clock"></i> <?php echo $time_start; ?></span>
                        <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($event['location']); ?></span>
                        <span><i class="fas fa-user-tie"></i> <?php echo htmlspecialchars($event['organizer_name']); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Event Details Content -->
    <section class="event-detail-content">
        <div class="container">
            <a href="index.php" class="back-link">
                <i class="fas fa-arrow-right"></i> العودة للصفحة الرئيسية
            </a>

            <?php if ($success_message): ?>
                <div class="alert-msg alert-success"><i class="fas fa-check-circle"></i> <?php echo $success_message; ?></div>
            <?php endif; ?>
            <?php if ($error_message): ?>
                <div class="alert-msg alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?></div>
            <?php endif; ?>

            <div class="event-detail-grid">
                <!-- Left Column - Details -->
                <div>
                    <!-- Description -->
                    <div class="detail-card">
                        <h3 class="detail-card-title">
                            <i class="fas fa-align-right"></i> وصف الفعالية
                        </h3>
                        <div class="event-description"><?php echo nl2br(htmlspecialchars($event['description'])); ?></div>
                    </div>

                    <!-- Event Info -->
                    <div class="detail-card">
                        <h3 class="detail-card-title">
                            <i class="fas fa-info-circle"></i> معلومات الفعالية
                        </h3>
                        <div class="info-list">
                            <div class="info-item">
                                <div class="info-icon" style="background: linear-gradient(135deg, #667eea, #764ba2);">
                                    <i class="fas fa-calendar-day"></i>
                                </div>
                                <div class="info-item-text">
                                    <small>تاريخ البداية</small>
                                    <strong><?php echo date('Y/m/d - h:i A', strtotime($event['start_datetime'])); ?></strong>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-icon" style="background: linear-gradient(135deg, #FF6B6B, #EE5A6F);">
                                    <i class="fas fa-calendar-check"></i>
                                </div>
                                <div class="info-item-text">
                                    <small>تاريخ النهاية</small>
                                    <strong><?php echo date('Y/m/d - h:i A', strtotime($event['end_datetime'])); ?></strong>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-icon" style="background: linear-gradient(135deg, #4ECDC4, #44A08D);">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="info-item-text">
                                    <small>المدة</small>
                                    <strong>
                                        <?php
                                        if ($duration->d > 0) echo $duration->d . ' يوم ';
                                        if ($duration->h > 0) echo $duration->h . ' ساعة ';
                                        if ($duration->i > 0) echo $duration->i . ' دقيقة';
                                        ?>
                                    </strong>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-icon" style="background: linear-gradient(135deg, #F093FB, #F5576C);">
                                    <i class="fas fa-map-marker-alt"></i>
                                </div>
                                <div class="info-item-text">
                                    <small>الموقع</small>
                                    <strong><?php echo htmlspecialchars($event['location']); ?></strong>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-icon" style="background: linear-gradient(135deg, #a78bfa, #7c3aed);">
                                    <i class="fas fa-user-tie"></i>
                                </div>
                                <div class="info-item-text">
                                    <small>المنظم</small>
                                    <strong><?php echo htmlspecialchars($event['organizer_name']); ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column - Sidebar -->
                <div>
                    <!-- Capacity Card -->
                    <div class="detail-card">
                        <h3 class="detail-card-title">
                            <i class="fas fa-users"></i> السعة والتسجيلات
                        </h3>

                        <div class="capacity-grid">
                            <div class="capacity-box">
                                <?php if ($has_limit): ?>
                                    <div class="capacity-value" style="color: #667eea;"><?php echo intval($event['capacity']); ?></div>
                                <?php else: ?>
                                    <div class="capacity-value" style="color: #10b981; font-size: 1.5rem;"><i class="fas fa-infinity"></i></div>
                                <?php endif; ?>
                                <div class="capacity-label">الحد الأقصى</div>
                            </div>
                            <div class="capacity-box">
                                <?php if ($has_limit): ?>
                                    <div class="capacity-value" style="color: <?php echo $event['available_seats'] > 0 ? '#10b981' : '#ef4444'; ?>;">
                                        <?php echo max(0, $event['available_seats']); ?>
                                    </div>
                                    <div class="capacity-label">المقاعد المتبقية</div>
                                <?php else: ?>
                                    <div class="capacity-value" style="color: #10b981; font-size: 1.5rem;"><i class="fas fa-check-circle"></i></div>
                                    <div class="capacity-label">متاح للتسجيل</div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.4rem;">
                            <span style="font-size: 0.8rem; color: #94a3b8; font-weight: 600;">المسجلين</span>
                            <?php if ($has_limit): ?>
                            <span style="font-size: 0.8rem; font-weight: 800; color: #764ba2;">
                                <?php echo $event['registrations_count']; ?> / <?php echo intval($event['capacity']); ?>
                            </span>
                            <?php else: ?>
                            <span style="font-size: 0.8rem; font-weight: 800; color: #10b981;">
                                <?php echo $event['registrations_count']; ?> مسجل
                            </span>
                            <?php endif; ?>
                        </div>
                        <div class="seats-track">
                            <?php if ($has_limit): ?>
                            <div class="seats-fill <?php echo $seat_percent >= 100 ? 'full' : ($seat_percent >= 80 ? 'almost-full' : ''); ?>"
                                 style="width: <?php echo min($seat_percent, 100); ?>%;"></div>
                            <?php else: ?>
                            <div class="seats-fill" style="width: <?php echo min(($event['registrations_count'] > 0 ? 30 : 0), 100); ?>%; opacity:0.4;"></div>
                            <?php endif; ?>
                        </div>

                        <?php if ($has_limit && $event['available_seats'] <= 5 && $event['available_seats'] > 0): ?>
                            <div class="seats-warning low">
                                <i class="fas fa-exclamation-triangle"></i>
                                تبقى <?php echo $event['available_seats']; ?> مقاعد فقط!
                            </div>
                        <?php elseif ($has_limit && $event['available_seats'] <= 0): ?>
                            <div class="seats-warning empty">
                                <i class="fas fa-times-circle"></i>
                                الفعالية ممتلئة بالكامل
                            </div>
                        <?php endif; ?>

                        <!-- Register Box -->
                        <div class="register-box">
                            <?php if ($isLoggedIn && $user): ?>
                                <?php if ($is_registered): ?>
                                    <button class="btn-register-event btn-done" disabled>
                                        <i class="fas fa-check-circle"></i> أنت مسجل في هذه الفعالية
                                    </button>
                                    <p style="text-align: center; margin-top: 0.75rem; color: #94a3b8; font-size: 0.85rem;">
                                        حالة التسجيل: <strong style="color: #667eea;"><?php echo htmlspecialchars($registration_status); ?></strong>
                                    </p>
                                <?php elseif ($has_limit && $event['available_seats'] <= 0): ?>
                                    <button class="btn-register-event btn-full" disabled>
                                        <i class="fas fa-times-circle"></i> الفعالية ممتلئة
                                    </button>
                                <?php elseif (strtotime($event['start_datetime']) <= time()): ?>
                                    <button class="btn-register-event btn-full" disabled>
                                        <i class="fas fa-clock"></i> انتهى وقت التسجيل
                                    </button>
                                <?php elseif ($user['role'] === 'student'): ?>
                                    <form method="POST">
                                        <button type="submit" name="register_event" class="btn-register-event btn-go">
                                            <i class="fas fa-user-plus"></i> سجّل في الفعالية
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <button class="btn-register-event btn-full" disabled>
                                        <i class="fas fa-info-circle"></i> التسجيل للطلاب فقط
                                    </button>
                                <?php endif; ?>
                            <?php else: ?>
                                <a href="auth/login.php?redirect=<?php echo urlencode('event-details.php?id=' . $event_id); ?>" class="btn-login-to-register">
                                    <i class="fas fa-sign-in-alt"></i> سجّل دخولك للاشرواق
                                </a>
                                <p style="text-align: center; margin-top: 0.75rem; color: #94a3b8; font-size: 0.85rem;">
                                    ليس لديك حساب؟ <a href="auth/register.php" style="color: #667eea; font-weight: 700;">أنشئ حساب</a>
                                </p>
                            <?php endif; ?>
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
        // Mobile Navbar Toggle
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
