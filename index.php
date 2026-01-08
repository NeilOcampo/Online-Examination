<?php
error_reporting(0);
session_start();

// --- IMPORTANT: Assume this path leads to your PDO database connection setup ($dbh) ---
include(__DIR__ . '/includes/config.php'); 

// --- NEW: LOGIC PARA SA CONTACT FORM ---
// Ito ang magsasave ng data kapag pinindot ang "Send Message"
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['full_name'])) {
    $name = $_POST['full_name'];
    $email = $_POST['email'];
    $message = $_POST['message'];
    
    $sql_contact = "INSERT INTO contact_messages (full_name, email, message, status, date_sent) VALUES (?, ?, ?, 'unread', NOW())";
    $stmt_contact = $dbh->prepare($sql_contact);
    $stmt_contact->execute([$name, $email, $message]);
    
    // I-redirect pabalik para hindi mag-duplicate ang send sa refresh
    header("Location: index.php?sent=1#contact");
    exit();
}

// --- FETCH ACTIVE EXAM SCHEDULES ---
$sql_public_schedule = "
    SELECT 
        subject, 
        exam_date, 
        start_time, 
        duration, 
        target_level 
    FROM 
        exam_schedule 
    WHERE 
        is_active = TRUE 
    ORDER BY 
        exam_date ASC, 
        start_time ASC
    LIMIT 5
";
try {
    // Execute the query
    $query_public_schedule = $dbh->query($sql_public_schedule);
    $public_schedules = $query_public_schedule->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Fallback if table doesn't exist or connection fails
    $public_schedules = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Examination System | SLIS</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

<style>
/* ------------------------------------------------------------------- */
/* ROOT COLORS AND GLOBAL */
/* ------------------------------------------------------------------- */
:root {
    --primary-color: #3b82f6; /* Tailwind Blue-500 */
    --secondary-color: #14b8a6; /* Tailwind Teal-500 */
    --dark-bg: #1e293b; /* Tailwind Slate-800 */
    --light-bg: #f5f7fa; 
}

body {
    font-family: 'Poppins', sans-serif;
    background-color: var(--light-bg);
    scroll-behavior: smooth;
    color: #333;
}
h2 { font-weight: 700; }

/* ------------------------------------------------------------------- */
/* NAVBAR */
/* ------------------------------------------------------------------- */
.navbar {
    background: rgba(30, 30, 30, 0.75) !important;
    backdrop-filter: blur(8px);
    transition: all 0.4s ease;
    border-bottom: 3px solid transparent;
}
.navbar.scrolled {
    background: var(--dark-bg) !important; /* Deeper color on scroll */
    padding-top: 8px;
    padding-bottom: 8px;
    border-bottom: 3px solid var(--secondary-color);
}
.navbar-brand {
    font-size: 1.6rem;
    font-weight: 800;
    color: #fff !important;
}
.nav-link {
    font-weight: 500;
    letter-spacing: .5px;
    transition: 0.3s;
    color: rgba(255, 255, 255, 0.8) !important;
}
.nav-link:hover, .nav-link.active {
    color: var(--secondary-color) !important; /* Teal accent */
    transform: translateY(-2px);
}
.navbar-nav .nav-item {
    margin: 0 10px;
}
.btn-outline-light {
    border-color: rgba(255, 255, 255, 0.6);
    color: #fff;
}
.btn-outline-light:hover {
    background-color: rgba(255, 255, 255, 0.1);
    color: var(--secondary-color);
}
.btn-warning {
    background-color: #f59e0b; /* Professional Gold/Amber */
    border-color: #f59e0b;
    font-weight: 600;
}
.btn-warning:hover {
    background-color: #d97706;
    border-color: #d97706;
}

/* ------------------------------------------------------------------- */
/* CAROUSEL / HERO SECTION */
/* ------------------------------------------------------------------- */
.carousel-item {
    height: 95vh;
    background-size: cover;
    background-position: center;
}
/* ITO ANG IN-UPDATE: Ginawa na lang 0.7 ang opacity para mas manipis kaysa 0.8 */
.carousel-item::before {
    content: ''; 
    position: absolute;
    top:0;left:0;right:0;bottom:0;
    background: rgba(0,0,0,0.4); /* Mas manipis na overlay (70% dark) */
}
.carousel-caption {
    padding-bottom: 120px; /* Lower caption for better visual balance */
}
.carousel-caption h5 {
    font-size: 4.5rem;
    font-weight: 900;
    text-shadow: none;
    /* Ginamit ang linear-gradient para sa mas eleganteng glow effect */
    background: linear-gradient(to right, #ffffff 30%, #a7f3d0 70%); /* White to light teal */
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    animation: glow 2s infinite alternate;
}
@keyframes glow {
    from { text-shadow: 0 0 5px rgba(255, 255, 255, 0.8); }
    to { text-shadow: 0 0 15px var(--secondary-color); }
}

.carousel-caption p {
    font-size: 1.4rem;
    font-weight: 500;
    letter-spacing: 0.5px;
    text-shadow: 0 0 10px #000;
    max-width: 700px;
    margin: 0 auto 20px auto;
}
.btn-primary, .btn-info {
    font-weight: 600;
    letter-spacing: 0.5px;
}
/* Solid Background para sa Login Button */
.btn-login-custom {
    background-color: var(--primary-color) !important;
    color: white !important;
    border: none;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-login-custom:hover {
    background-color: #2563eb !important; /* Mas madilim na asul pag na-hover */
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
}

/* ------------------------------------------------------------------- */
/* FEATURE CARDS */
/* ------------------------------------------------------------------- */
.feature-box {
    border-radius: 15px;
    transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94); /* Smoother transition */
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
    height: 100%; /* Ensure uniform height in row */
}
.feature-box:hover {
    transform: translateY(-8px);
    box-shadow: 0 15px 30px rgba(59, 130, 246, 0.25) !important; /* Blue glow on hover */
}
.feature-box .display-5 {
    color: var(--primary-color) !important;
}

/* ------------------------------------------------------------------- */
/* SCHEDULE CARD */
/* ------------------------------------------------------------------- */
.schedule-card {
    border: none;
    border-left: 8px solid var(--primary-color);
    transition: 0.3s;
    border-radius: 15px;
    box-shadow: 0 8px 20px rgba(0,0,0,0.1);
    background: #fff;
}
.schedule-card:hover {
    transform: translateX(5px);
    box-shadow: 0 12px 30px rgba(0,0,0,0.15);
}
.table thead {
    background-color: var(--dark-bg);
    color: white;
}
.badge.bg-success-subtle {
    background-color: #d1fae5 !important; /* Lighter teal/green background */
    color: #065f46 !important; /* Darker green text */
    font-size: 0.9em;
    padding: 8px 12px;
    border-radius: 8px;
}

/* ------------------------------------------------------------------- */
/* CONTACT FORM */
/* ------------------------------------------------------------------- */
#contact {
    background-color: #fff; /* Make contact section pop */
}
form {
    border-radius: 15px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    background: var(--light-bg);
    animation: softFade 1s ease forwards;
}
.form-control-lg {
    border-radius: 10px;
}

/* ------------------------------------------------------------------- */
/* FOOTER */
/* ------------------------------------------------------------------- */
/* FOOTER GLOBAL STYLING */
footer {
    background: #1e293b; /* Dark slate */
    color: #cbd5e1;
    padding-top: 60px;
    padding-bottom: 30px;
    border-top: 4px solid #3b82f6;
}

/* Footer Headers */
footer h5 {
    color: #ffffff;
    font-size: 1.1rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 25px;
    position: relative;
}

/* Blue line indicator sa ilalim ng headers */
footer h5::after {
    content: '';
    position: absolute;
    left: 0;
    bottom: -8px;
    width: 40px;
    height: 3px;
    background: #3b82f6;
}

/* Right alignment for Large screens, Left for mobile */
@media (min-width: 992px) {
    .text-lg-end-custom h5::after {
        left: auto;
        right: 0;
    }
}

/* Social Icons Styling */
.social-icon {
    width: 60px; /* Mas malaki na */
    height: 60px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px; /* Rounded square para modern */
    background: rgba(255, 255, 255, 0.05);
    color: white;
    font-size: 1.8rem; /* Pinalaki ang logo */
    transition: all 0.3s ease;
    text-decoration: none;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.social-icon:hover {
    background: #3b82f6;
    color: white;
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(59, 130, 246, 0.3);
    border-color: #3b82f6;
}

.footer-link {
    color: #94a3b8;
    text-decoration: none;
    transition: 0.3s;
    display: block;
    margin-bottom: 12px;
}

.footer-link:hover {
    color: #3b82f6;
    padding-left: 5px;
}

.contact-info i {
    width: 25px;
    color: #3b82f6;
}

/* ANIMATION ON SCROLL */
[data-aos] {
    transition: all 1.2s cubic-bezier(0.25, 0.46, 0.45, 0.94) !important;
}

</style>
</head>

<body data-bs-spy="scroll" data-bs-target="#mainNav" tabindex="0">

<nav class="navbar navbar-expand-lg navbar-dark fixed-top shadow-lg" id="mainNav">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="#">
            <img src="images/OIP (22).jpg" alt="Logo" style="height: 40px; width: 40px; object-fit: cover;" class="me-2 rounded-circle">
            SLIS Online Examination
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav mx-auto">
                <li class="nav-item"><a class="nav-link active" href="#mainCarousel">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="#about">About</a></li>
                <li class="nav-item"><a class="nav-link" href="#features">Features</a></li>
                <li class="nav-item"><a class="nav-link" href="#schedule">Schedule</a></li>
                <li class="nav-item"><a class="nav-link" href="#contact">Contact</a></li>
            </ul>

            <div class="d-flex align-items-center">
                <a href="login.php" class="btn btn-login-custom btn-sm me-3 rounded-pill px-4">Login</a>
                <a href="admin/dashboard.php" class="btn btn-warning btn-sm rounded-pill px-4">Admin Access</a>
            </div>
        </div>
    </div>
</nav>

<div id="mainCarousel" class="carousel slide" data-bs-ride="carousel">
    <div class="carousel-indicators">
        <button type="button" data-bs-target="#mainCarousel" data-bs-slide-to="0" class="active"></button>
        <button type="button" data-bs-target="#mainCarousel" data-bs-slide-to="1"></button>
        <button type="button" data-bs-target="#mainCarousel" data-bs-slide-to="2"></button>
    </div>

    <div class="carousel-inner">

        <div class="carousel-item active" style="background-image:url('images/477440891_122255555288007372_1843571032537471815_n.jpg');">
            <div class="carousel-caption" data-aos="fade-right"> 
                <h5>Secure and Smart Online Exams</h5>
                <p>Conduct, take, and evaluate exams anytime, anywhere with confidence.</p>
                <a href="login.php" class="btn btn-primary btn-lg mt-3 rounded-pill shadow px-5 py-2">Start Examination</a>
            </div>
        </div>

        <div class="carousel-item" style="background-image:url('images/472703203_122248414988007372_1125441470978547384_n.jpg');">
            <div class="carousel-caption" data-aos="zoom-in">
                <h5>Automated Evaluation and Reporting</h5>
                <p>Instant scoring, detailed performance tracking, and reliable results.</p>
                <a href="#features" class="btn btn-warning btn-lg mt-3 rounded-pill shadow px-5 py-2">View Features</a>
            </div>
        </div>

        <div class="carousel-item" style="background-image:url('images/480170956_122256622706007372_1976133218210424961_n.jpg');">
            <div class="carousel-caption" data-aos="fade-left">
                <h5>Reliable, Responsive, and User-Friendly</h5>
                <p>A simple, fast, and adaptive platform optimized for all devices.</p>
                <a href="#contact" class="btn btn-info btn-lg mt-3 rounded-pill shadow px-5 py-2">Contact Support</a>
            </div>
        </div>

    </div>

    <button class="carousel-control-prev" type="button" data-bs-target="#mainCarousel" data-bs-slide="prev">
        <span class="carousel-control-prev-icon"></span>
    </button>
    <button class="carousel-control-next" type="button" data-bs-target="#mainCarousel" data-bs-slide="next">
        <span class="carousel-control-next-icon"></span>
    </button>
</div>

<section id="about" class="py-5 overflow-hidden" style="background: var(--light-bg); position: relative;">
    <div class="container py-5">
        <div class="row align-items-stretch">
            
            <div class="col-lg-6 mb-5 mb-lg-0" data-aos="fade-right">
                <div class="position-relative h-100"> <div style="position: absolute; top: -20px; left: -20px; width: 100px; height: 100px; background: var(--secondary-color); border-radius: 50%; opacity: 0.2; z-index: 0;"></div>
                    
                    <img src="images/examnaman.jpg" 
                         alt="SLIS Campus" 
                         class="img-fluid rounded-4 shadow-lg position-relative h-100 w-100" 
                         style="z-index: 1; border: 8px solid #fff; object-fit: cover;">
                    
                    <div class="bg-white p-3 rounded-3 shadow position-absolute bottom-0 start-0 mb-4 ms-n3 d-none d-md-block" style="z-index: 2; border-left: 5px solid var(--primary-color);">
                        <h5 class="fw-bold mb-0 text-primary">Secure & Fair</h5>
                        <small class="text-muted">Online Testing Standards</small>
                    </div>
                </div>
            </div>

            <div class="col-lg-6" data-aos="fade-left">
                <div class="p-4 p-md-5 bg-white shadow-lg rounded-4 border-top border-primary border-5 h-100 d-flex flex-column justify-content-center">
                    <span class="badge bg-primary-subtle text-primary px-3 py-2 rounded-pill mb-3" style="width: fit-content;">Our Vision</span>
                    <h2 class="fw-bold text-dark mb-4">Empowering Education Through Technology</h2>
                    
                    <p class="text-muted fs-5 lh-base">
The SLIS Online Examination System is more than just a tool; it is a solution for the modern era. It aims to bridge the security of traditional exams with the speed and efficiency of modern technology.
                    </p>
                    
                    <hr class="my-4 opacity-50">
                    
                    <div class="d-flex mb-4">
                        <div class="flex-shrink-0 me-3">
                            <div class="bg-light p-3 rounded-circle text-primary">
                                <i class="fa-solid fa-bolt fs-4"></i>
                            </div>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-1">Fast & Efficient</h6>
                            <p class="small text-muted mb-0">Instant generation of results and automated grade analytics for teachers.</p>
                        </div>
                    </div>

                    <div class="d-flex">
                        <div class="flex-shrink-0 me-3">
                            <div class="bg-light p-3 rounded-circle text-primary">
                                <i class="fa-solid fa-shield-halved fs-4"></i>
                            </div>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-1">Anti-Cheat Protocols</h6>
                            <p class="small text-muted mb-0">Equipped with security features to maintain the integrity of every assessment.</p>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>

<section id="features" class="py-5" style="background:var(--light-bg);">
    <div class="container text-center">

        <h2 class="mb-4 text-dark" data-aos="fade-down">Key Features</h2>
        <p class="text-muted mb-5 fs-5" data-aos="fade-up">Experience a seamless and efficient online examination process.</p>

        <div class="row g-4">

            <div class="col-md-4" data-aos="zoom-in">
                <div class="p-5 bg-white rounded-4 shadow-sm feature-box">
                    <div class="display-5 text-primary mb-3">
                        <i class="fa-solid fa-file-circle-plus"></i>
                    </div>
                    <h5 class="fw-bold">Effortless Exam Creation</h5>
                    <p class="text-muted">Teachers can create and manage complex exams and questions with intuitive controls.</p>
                </div>
            </div>

            <div class="col-md-4" data-aos="zoom-in" data-aos-delay="200">
                <div class="p-5 bg-white rounded-4 shadow-sm feature-box">
                    <div class="display-5 text-primary mb-3">
                        <i class="fa-solid fa-clock"></i>
                    </div>
                    <h5 class="fw-bold">Secure Timed Environment</h5>
                    <p class="text-muted">Built-in timers and security features ensure a controlled and fair testing environment.</p>
                </div>
            </div>

            <div class="col-md-4" data-aos="zoom-in" data-aos-delay="400">
                <div class="p-5 bg-white rounded-4 shadow-sm feature-box">
                    <div class="display-5 text-primary mb-3">
                        <i class="fa-solid fa-chart-line"></i>
                    </div>
                    <h5 class="fw-bold">Instant Results & Analytics</h5>
                    <p class="text-muted">Automatic scoring provides immediate feedback and detailed performance reports.</p>
                </div>
            </div>

        </div>

    </div>
</section>

<section id="schedule" class="container text-center py-5" data-aos="fade-up">
    <h2 class="fw-bold text-dark">📅 Official Exam Schedule</h2>
    <p class="lead text-muted">Tingnan ang ilan sa mga nakatakdang pagsusulit sa kasalukuyan.</p>

    <div class="card p-4 schedule-card mx-auto" style="max-width:900px;" data-aos="fade-right">
        <h5 class="fw-bold text-primary mb-3">Publicly Announced Schedules (Top 5)</h5>
        
        <div class="table-responsive mt-4">
            <table class="table table-hover align-middle">
                <thead class="bg-dark text-white">
                    <tr>
                        <th>Subject</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Duration</th>
                        <th>Target Level</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($public_schedules)): ?>
                        <?php foreach ($public_schedules as $schedule): ?>
                            <tr>
                                <td class="fw-bold text-dark"><?php echo htmlspecialchars($schedule['subject']); ?></td>
                                <td>
                                    <span class="badge bg-success-subtle">
                                        <?php echo date("M d, Y", strtotime($schedule['exam_date'])); ?> 
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-secondary text-white">
                                        <?php echo date("h:i A", strtotime($schedule['start_time'])); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($schedule['duration']); ?></td>
                                <td><?php echo htmlspecialchars($schedule['target_level']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted p-4">
                                Walang nakatalagang active na schedule sa kasalukuyan.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <p class="mt-3 text-muted fst-italic">Mag-login para makita ang buong listahan at detalye ng mga exam.</p>
    </div>
</section>

<section id="contact" class="container-fluid text-center py-5" style="background:#fff;">
    <div class="container"> <h2 class="fw-bold mb-3 text-dark">Get in Touch</h2>
        <p class="text-muted fs-5">We’d love to hear from you! Mag-iwan ng mensahe sa ibaba.</p>

        <div class="row justify-content-center">
            <div class="col-lg-7" data-aos="fade-up">
                <form action="index.php" method="POST" class="p-5 shadow-sm" style="background: var(--light-bg); border-radius: 15px;">
                    <?php if(isset($_GET['sent'])): ?>
                        <div class="alert alert-success">Message sent successfully!</div>
                    <?php endif; ?>
                    <h4 class="mb-4 text-primary fw-bold">Send us a message</h4>
                    
                    <div class="mb-4 text-start">
                        <label class="fw-semibold mb-2">Full Name</label>
                        <input name="full_name" class="form-control form-control-lg" type="text" placeholder="Enter your full name" required>
                    </div>
                    
                    <div class="mb-4 text-start">
                        <label class="fw-semibold mb-2">Email</label>
                        <input name="email" class="form-control form-control-lg" type="email" placeholder="Enter your email" required>
                    </div>
                    
                    <div class="mb-4 text-start">
                        <label class="fw-semibold mb-2">Message</label>
                        <textarea name="message" class="form-control form-control-lg" rows="4" placeholder="Type your message..." required></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold rounded-pill py-2">Send Message</button>
                </form>
            </div>
        </div>

        <div class="row text-center mt-5" data-aos="fade-up">
            <div class="col-md-4">
                <h6 class="fw-semibold text-dark">📍 Address</h6>
                <p class="text-muted">Pampanga, Philippines</p>
            </div>
            <div class="col-md-4">
                <h6 class="fw-semibold text-dark">📧 Email</h6>
                <p class="text-muted">neilocampo580@gmail.com</p>
            </div>
            <div class="col-md-4">
                <h6 class="fw-semibold text-dark">📞 Phone</h6>
                <p class="text-muted">+63 910 805 5439</p>
            </div>
        </div>
        
    </div> </section>

<footer>
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-4 col-md-6">
                <h5>SLIS Online Exam</h5>
                <p class="small lh-lg mb-4">
                    Providing secure, reliable, and innovative examination services for the Santa Lucia Integrated School community.
                </p>
                <div class="contact-info small">
                    <p class="mb-2"><i class="fa-solid fa-location-dot"></i> Pampanga, Philippines</p>
                    <p class="mb-2"><i class="fa-solid fa-envelope"></i> neilocampo580@gmail.com</p>
                    <p class="mb-0"><i class="fa-solid fa-phone"></i> +63 910 805 5439</p>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 px-lg-5">
                <h5>Quick Links</h5>
                <nav>
                    <a href="#about" class="footer-link small">About Us</a>
                    <a href="#features" class="footer-link small">System Features</a>
                    <a href="#schedule" class="footer-link small">Exam Schedule</a>
                    <a href="login.php" class="footer-link small">Student Portal</a>
                </nav>
            </div>

            <div class="col-lg-5 col-md-12 text-lg-end text-lg-end-custom">
                <h5>School Community</h5>
                <p class="small mb-4">Connect with our official pages for the latest announcements.</p>
                
                <div class="d-flex justify-content-lg-end gap-3 mb-4">
                    <a href="https://www.facebook.com/profile.php?id=61550221173431" target="_blank" class="social-icon" title="Facebook">
                        <i class="fa-brands fa-facebook"></i>
                    </a>
                    <a href="https://m.me/YOUR_SCHOOL_PAGE" target="_blank" class="social-icon" title="Messenger">
                        <i class="fa-brands fa-facebook-messenger"></i>
                    </a>
                </div>
                
                <div class="school-label">
                    <p class="fw-bold text-white mb-0">Santa Lucia Integrated School</p>
                    <small class="text-primary fw-semibold">Official Facebook Presence</small>
                </div>
            </div>
        </div>

        <hr class="mt-5 mb-4 opacity-10">
        <div class="row">
            <div class="col-md-12 text-center">
                <p class="small opacity-50 mb-0">
                    &copy; <?php echo date("Y"); ?> <strong>SLIS</strong>. All Rights Reserved. <br class="d-md-none">
                    Designed by <span class="text-white">Neil Ocampo And Team</span>
                </p>
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
AOS.init({ once:true, duration: 1000 }); // Smoother and longer duration

/* NAVBAR SHRINK ON SCROLL */
window.addEventListener("scroll", function() {
    const nav = document.getElementById("mainNav");
    if (window.scrollY > 80) { // Adjusted scroll point
        nav.classList.add("scrolled");
    } else {
        nav.classList.remove("scrolled");
    }
});

/* Smooth scrolling for anchor links */
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        // Only apply smooth scroll if the link is NOT the carousel indicator
        if (!this.closest('.carousel-indicators')) {
             e.preventDefault();
            const targetId = this.getAttribute('href');
            const targetElement = document.querySelector(targetId);
            
            if (targetElement) {
                const navbarHeight = document.querySelector('.navbar').offsetHeight;
                // Add a little extra padding (e.g., 20px)
                const offsetPosition = targetElement.offsetTop - navbarHeight - 20; 

                window.scrollTo({
                    top: offsetPosition,
                    behavior: "smooth"
                });
            }
        }
    });
});
</script>

</body>
</html>