<?php
session_start();
require_once 'config.php';

// If user is logged in, redirect to homepage
// if (isset($_SESSION['user_id'])) {
//     header("Location: homepage.php");
//     exit();
// }

if (isset($_SESSION['user_id'])) {
  session_destroy();
}


// Function to fetch content from the database
function getContent($conn, $pageName, $sectionName, $default = '') {
    $stmt = $conn->prepare("SELECT content_text FROM content WHERE page_name = ? AND section_name = ?");
    $stmt->bind_param("ss", $pageName, $sectionName);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return ($row['content_text']);
    }
    return $default;
}

// Always use images from /images if the filename exists there
function getImageFromImagesDir($filename, $fallback) {
    $imagesDir = __DIR__ . '/images/';
    $filenameOnly = basename($filename);
    $fullPath = $imagesDir . $filenameOnly;
    if (file_exists($fullPath)) {
        return 'images/' . $filenameOnly;
    }
    return $fallback;
}

// Fetch content for the index page
$heroTitle = getContent($conn, 'index', 'hero_title', 'Your Health, My Priority');
$heroSubtitle = getContent($conn, 'index', 'hero_subtitle', 'Comprehensive Healthcare for the WMSU Community');
$servicesMainTitle = getContent($conn, 'index', 'services_main_title', 'OUR SERVICES');
$service1Title = getContent($conn, 'index', 'service_1_title', 'PRIMARY CARE');
$service1Desc = getContent($conn, 'index', 'service_1_desc', 'Comprehensive medical care for routine check-ups, illness treatment, and health management.');
$service2Title = getContent($conn, 'index', 'service_2_title', 'PHARMACY');
$service2Desc = getContent($conn, 'index', 'service_2_desc', 'Convenient access to prescription medications and expert pharmacist advice.');
$service3Title = getContent($conn, 'index', 'service_3_title', 'SCREENINGS');
$service3Desc = getContent($conn, 'index', 'service_3_desc', 'Early detection through regular screenings for common health concerns.');
$service4Title = getContent($conn, 'index', 'service_4_title', 'DENTAL CARE');
$service4Desc = getContent($conn, 'index', 'service_4_desc', 'Oral health services, including dental check-ups, cleanings, and treatments.');
$service5Title = getContent($conn, 'index', 'service_5_title', 'VACCINATIONS');
$service5Desc = getContent($conn, 'index', 'service_5_desc', 'Protective immunizations for various diseases, administered by qualified professionals.');
$service6Title = getContent($conn, 'index', 'service_6_title', 'EDUCATION');
$service6Desc = getContent($conn, 'index', 'service_6_desc', 'Empowering students with health knowledge through workshops and consultations.');
$contactTelephone = getContent($conn, 'index', 'contact_telephone', '(062) 991-6736');
$contactEmail = getContent($conn, 'index', 'contact_email', 'healthservices@wmsu.edu.ph');
$contactLocation = getContent($conn, 'index', 'contact_location', 'Health Services Building, WMSU Campus, Zamboanga City, Philippines');
$footerText = getContent($conn, 'index', 'footer_text', '© 2025 Western Mindanao State University Health Services. All rights reserved. | wmsu.edu.ph');

// Function to get image from database
function getImage($conn, $pageName, $sectionName, $default = '') {
    $stmt = $conn->prepare("SELECT image_path, image_alt FROM images WHERE page_name = ? AND section_name = ?");
    $stmt->bind_param("ss", $pageName, $sectionName);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        // Check if path starts with /uploads/ and adjust if necessary
        if (strpos($row['image_path'], '/uploads/') === 0) {
            return $row;
        }
        
        // If path is relative but missing the leading slash
        if (strpos($row['image_path'], 'CMS/uploads/') === 0) {
            $row['image_path'] = '/' . $row['image_path'];
            return $row;
        }
        
        // If path is stored as absolute filesystem path
        $docRoot = $_SERVER['DOCUMENT_ROOT'];
        if (strpos($row['image_path'], $docRoot) === 0) {
            $row['image_path'] = str_replace($docRoot, '', $row['image_path']);
            return $row;
        }
        
        error_log("Image path format unexpected: " . $row['image_path']);
    }
    
    return ['image_path' => $default, 'image_alt' => ''];
}

// Fetch images
$logoImage = getImage($conn, 'index', 'logo', 'images/clinic.png');
$logoImage['image_path'] = getImageFromImagesDir($logoImage['image_path'], 'images/clinic.png');
$heroImage = getImage($conn, 'index', 'hero_background', 'images/12.jpg');
$heroImage['image_path'] = getImageFromImagesDir($heroImage['image_path'], 'images/12.jpg');
// Fallback for hero background if missing or not in uploads/images/backgrounds
if (!file_exists(__DIR__ . '/' . ltrim($heroImage['image_path'], '/')) || strpos($heroImage['image_path'], 'uploads/images/backgrounds/') === 0 && !file_exists(__DIR__ . '/' . ltrim($heroImage['image_path'], '/'))) {
    $heroImage['image_path'] = 'images/12.jpg';
}

// Display all images in /images directory
$imagesDir = __DIR__ . '/images';
$imageFiles = [];
if (is_dir($imagesDir)) {
    $allFiles = scandir($imagesDir);
    foreach ($allFiles as $file) {
        if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $file)) {
            $imageFiles[] = $file;
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="ie=edge">
  <title>WMSU Health Services</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="apple-touch-icon" sizes="180x180" href="images/apple-touch-icon.png">
  <link rel="icon" type="image/png" sizes="32x32" href="images/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="images/favicon-16x16.png">
  <link rel="manifest" href="images/site.webmanifest">
  <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;500;600;700;800;900&family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: {
            'cinzel': ['Cinzel', 'serif'],
            'poppins': ['Poppins', 'sans-serif'],
          },
          colors: {
            'wmsu-red': '#8B0000',
            'wmsu-gold': '#FFD700',
            'wmsu-dark': '#1a1a1a',
          },
          animation: {
            'fade-in': 'fadeIn 0.6s ease-in-out',
            'slide-up': 'slideUp 0.8s ease-out',
            'bounce-gentle': 'bounceGentle 2s infinite',
            'pulse-slow': 'pulseSlow 3s infinite',
          }
        }
      }
    }
  </script>
  
  <style>
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }
    
    @keyframes slideUp {
      from { opacity: 0; transform: translateY(40px); }
      to { opacity: 1; transform: translateY(0); }
    }
    
    @keyframes bounceGentle {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(-10px); }
    }
    
    @keyframes pulseSlow {
      0%, 100% { opacity: 1; }
      50% { opacity: 0.7; }
    }
    
    .glass-effect {
      background: rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.2);
    }
    
    .gradient-bg {
      background: linear-gradient(135deg, #8B0000 0%, #A52A2A 50%, #8B0000 100%);
    }
    
    .service-card-hover {
      transition: all 0.3s ease;
    }
    
    .service-card-hover:hover {
      transform: translateY(-8px) scale(1.02);
      box-shadow: 0 20px 40px rgba(139, 0, 0, 0.3);
    }
    
    .btn-hover {
      transition: all 0.3s ease;
    }
    
    .btn-hover:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 25px rgba(139, 0, 0, 0.3);
    }
    
    .scroll-smooth {
      scroll-behavior: smooth;
    }
  </style>
</head>
<body class="font-poppins bg-gray-50 scroll-smooth">  
  <div id="app">
    <!-- Header -->
    <header class="fixed top-0 w-full bg-white/95 backdrop-blur-md shadow-lg z-50 border-b border-gray-200">
      <div class="container mx-auto px-4 py-3">
        <div class="flex justify-between items-center">
          <!-- Logo -->
          <div class="flex items-center space-x-3 group cursor-pointer" onclick="scrollToTop()">
            <div class="relative">
              <img src="<?php echo htmlspecialchars($logoImage['image_path']); ?>" 
                   alt="<?php echo htmlspecialchars($logoImage['image_alt']); ?>"
                   class="w-12 h-12 object-contain transition-transform duration-300 group-hover:scale-110">
              <div class="absolute inset-0 bg-wmsu-gold/20 rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
            </div>
            <span class="font-cinzel text-xl font-bold text-wmsu-red group-hover:text-wmsu-dark transition-colors duration-300">
              WMSU Health Services
            </span>
          </div>

          <!-- Navigation Buttons -->
          <div class="flex items-center space-x-3">
            <button class="btn-hover bg-wmsu-red text-white px-6 py-2.5 rounded-full font-semibold text-sm uppercase tracking-wide flex items-center space-x-2 hover:bg-red-800" 
                    onclick="window.location.href='login.php'">
              <i class="fas fa-sign-in-alt"></i>
              <span>Login</span>
            </button>
            <button class="btn-hover bg-gradient-to-r from-wmsu-red to-red-700 text-white px-6 py-2.5 rounded-full font-semibold text-sm uppercase tracking-wide flex items-center space-x-2 hover:from-red-700 hover:to-red-800" 
                    onclick="window.location.href='signup.php'">
              <i class="fas fa-user-plus"></i>
              <span>Sign Up</span>
            </button>
          </div>
        </div>
      </div>
    </header>

    <!-- Main Content -->
    <main class="pt-20">
      <!-- Hero Section -->
      <section class="relative h-screen flex items-center justify-center overflow-hidden">
        <!-- Background Image with Overlay -->
        <div class="absolute inset-0">
          <img src="<?php echo htmlspecialchars($heroImage['image_path']); ?>?t=<?= time() ?>" 
               alt="Health Services Background" 
               class="w-full h-full object-cover">
          <div class="absolute inset-0 bg-gradient-to-r from-black/70 via-black/50 to-black/70"></div>
        </div>
        
        <!-- Hero Content -->
        <div class="relative z-10 text-center text-white px-4 max-w-4xl mx-auto">
          <h1 class="font-cinzel text-5xl md:text-7xl font-bold mb-6 animate-fade-in leading-tight">
            <?php echo $heroTitle; ?>
          </h1>
          <p class="font-poppins text-xl md:text-2xl mb-8 animate-fade-in animation-delay-200 opacity-90 leading-relaxed">
            <?php echo $heroSubtitle; ?>
          </p>
          
          <!-- CTA Buttons -->
          <div class="flex flex-col sm:flex-row gap-4 justify-center items-center animate-fade-in animation-delay-400">
            <button class="btn-hover bg-wmsu-gold text-wmsu-dark px-8 py-4 rounded-full font-bold text-lg uppercase tracking-wider flex items-center space-x-3 hover:bg-yellow-400 min-w-[200px] justify-center" 
                    onclick="scrollToSection('services')">
              <i class="fas fa-stethoscope text-xl"></i>
              <span>Our Services</span>
            </button>
            <button class="btn-hover glass-effect text-white px-8 py-4 rounded-full font-bold text-lg uppercase tracking-wider flex items-center space-x-3 hover:bg-white/20 min-w-[200px] justify-center" 
                    onclick="scrollToSection('contact')">
              <i class="fas fa-phone-alt text-xl"></i>
              <span>Contact Us</span>
            </button>
          </div>
        </div>
        
        <!-- Scroll Indicator -->
        <div class="absolute bottom-8 left-1/2 transform -translate-x-1/2 animate-bounce-gentle">
          <div class="w-6 h-10 border-2 border-white/50 rounded-full flex justify-center">
            <div class="w-1 h-3 bg-white/70 rounded-full mt-2 animate-pulse-slow"></div>
          </div>
        </div>
      </section>

      <!-- Services Section -->
      <section id="services" class="py-20 gradient-bg relative">
        <div class="container mx-auto px-4">
          <!-- Section Header -->
          <div class="text-center mb-16 animate-slide-up">
            <h2 class="font-cinzel text-4xl md:text-5xl font-bold text-white mb-4">
              <?php echo $servicesMainTitle; ?>
            </h2>
            <div class="w-24 h-1 bg-wmsu-gold mx-auto rounded-full"></div>
          </div>
          
          <!-- Services Grid -->
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <!-- Service 1 -->
            <div class="service-card-hover glass-effect rounded-2xl p-8 text-center group cursor-pointer" 
                 onclick="showServiceDetails('<?php echo $service1Title; ?>', '<?php echo $service1Desc; ?>')">
              <div class="w-20 h-20 bg-wmsu-gold/20 rounded-full flex items-center justify-center mx-auto mb-6 group-hover:bg-wmsu-gold/40 transition-all duration-300">
                <i class="fas fa-user-md text-3xl text-wmsu-gold group-hover:scale-110 transition-transform duration-300"></i>
              </div>
              <h3 class="font-cinzel text-xl font-semibold text-white mb-4 group-hover:text-wmsu-gold transition-colors duration-300">
                <?php echo $service1Title; ?>
              </h3>
              <p class="text-white/80 text-sm leading-relaxed">
                <?php echo $service1Desc; ?>
              </p>
            </div>
            
            <!-- Service 2 -->
            <div class="service-card-hover glass-effect rounded-2xl p-8 text-center group cursor-pointer" 
                 onclick="showServiceDetails('<?php echo $service2Title; ?>', '<?php echo $service2Desc; ?>')">
              <div class="w-20 h-20 bg-wmsu-gold/20 rounded-full flex items-center justify-center mx-auto mb-6 group-hover:bg-wmsu-gold/40 transition-all duration-300">
                <i class="fas fa-pills text-3xl text-wmsu-gold group-hover:scale-110 transition-transform duration-300"></i>
              </div>
              <h3 class="font-cinzel text-xl font-semibold text-white mb-4 group-hover:text-wmsu-gold transition-colors duration-300">
                <?php echo $service2Title; ?>
              </h3>
              <p class="text-white/80 text-sm leading-relaxed">
                <?php echo $service2Desc; ?>
              </p>
            </div>
            
            <!-- Service 3 -->
            <div class="service-card-hover glass-effect rounded-2xl p-8 text-center group cursor-pointer" 
                 onclick="showServiceDetails('<?php echo $service3Title; ?>', '<?php echo $service3Desc; ?>')">
              <div class="w-20 h-20 bg-wmsu-gold/20 rounded-full flex items-center justify-center mx-auto mb-6 group-hover:bg-wmsu-gold/40 transition-all duration-300">
                <i class="fas fa-heartbeat text-3xl text-wmsu-gold group-hover:scale-110 transition-transform duration-300"></i>
              </div>
              <h3 class="font-cinzel text-xl font-semibold text-white mb-4 group-hover:text-wmsu-gold transition-colors duration-300">
                <?php echo $service3Title; ?>
              </h3>
              <p class="text-white/80 text-sm leading-relaxed">
                <?php echo $service3Desc; ?>
              </p>
            </div>
            
            <!-- Service 4 -->
            <div class="service-card-hover glass-effect rounded-2xl p-8 text-center group cursor-pointer" 
                 onclick="showServiceDetails('<?php echo $service4Title; ?>', '<?php echo $service4Desc; ?>')">
              <div class="w-20 h-20 bg-wmsu-gold/20 rounded-full flex items-center justify-center mx-auto mb-6 group-hover:bg-wmsu-gold/40 transition-all duration-300">
                <i class="fas fa-tooth text-3xl text-wmsu-gold group-hover:scale-110 transition-transform duration-300"></i>
              </div>
              <h3 class="font-cinzel text-xl font-semibold text-white mb-4 group-hover:text-wmsu-gold transition-colors duration-300">
                <?php echo $service4Title; ?>
              </h3>
              <p class="text-white/80 text-sm leading-relaxed">
                <?php echo $service4Desc; ?>
              </p>
            </div>
            
            <!-- Service 5 -->
            <div class="service-card-hover glass-effect rounded-2xl p-8 text-center group cursor-pointer" 
                 onclick="showServiceDetails('<?php echo $service5Title; ?>', '<?php echo $service5Desc; ?>')">
              <div class="w-20 h-20 bg-wmsu-gold/20 rounded-full flex items-center justify-center mx-auto mb-6 group-hover:bg-wmsu-gold/40 transition-all duration-300">
                <i class="fas fa-syringe text-3xl text-wmsu-gold group-hover:scale-110 transition-transform duration-300"></i>
              </div>
              <h3 class="font-cinzel text-xl font-semibold text-white mb-4 group-hover:text-wmsu-gold transition-colors duration-300">
                <?php echo $service5Title; ?>
              </h3>
              <p class="text-white/80 text-sm leading-relaxed">
                <?php echo $service5Desc; ?>
              </p>
            </div>
            
            <!-- Service 6 -->
            <div class="service-card-hover glass-effect rounded-2xl p-8 text-center group cursor-pointer" 
                 onclick="showServiceDetails('<?php echo $service6Title; ?>', '<?php echo $service6Desc; ?>')">
              <div class="w-20 h-20 bg-wmsu-gold/20 rounded-full flex items-center justify-center mx-auto mb-6 group-hover:bg-wmsu-gold/40 transition-all duration-300">
                <i class="fas fa-book-medical text-3xl text-wmsu-gold group-hover:scale-110 transition-transform duration-300"></i>
              </div>
              <h3 class="font-cinzel text-xl font-semibold text-white mb-4 group-hover:text-wmsu-gold transition-colors duration-300">
                <?php echo $service6Title; ?>
              </h3>
              <p class="text-white/80 text-sm leading-relaxed">
                <?php echo $service6Desc; ?>
              </p>
            </div>
          </div>
        </div>
      </section>

      <!-- Contact & Info Section -->
      <section id="contact" class="py-20 bg-gray-50">
        <div class="container mx-auto px-4">
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
            <!-- Operating Hours -->
            <div class="animate-slide-up">
              <div class="bg-white rounded-2xl shadow-xl overflow-hidden hover:shadow-2xl transition-shadow duration-300">
                <div class="bg-gradient-to-r from-wmsu-red to-red-700 text-white p-6">
                  <div class="flex items-center space-x-3">
                    <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center">
                      <i class="far fa-clock text-xl"></i>
                    </div>
                    <h3 class="font-cinzel text-2xl font-semibold">Operating Hours</h3>
                  </div>
                </div>
                <div class="p-6 space-y-4">
                  <div class="flex justify-between items-center py-3 border-b border-gray-100 last:border-b-0">
                    <span class="font-semibold text-gray-700">Monday to Friday:</span>
                    <span class="text-wmsu-red font-medium"><?php echo getContent($conn, 'index', 'operating_hours_mon_fri', '8:00 AM - 5:00 PM'); ?></span>
                  </div>
                  <div class="flex justify-between items-center py-3 border-b border-gray-100 last:border-b-0">
                    <span class="font-semibold text-gray-700">Saturday:</span>
                    <span class="text-wmsu-red font-medium"><?php echo getContent($conn, 'index', 'operating_hours_saturday', '8:00 AM - 12:00 PM'); ?></span>
                  </div>
                  <div class="flex justify-between items-center py-3 border-b border-gray-100 last:border-b-0">
                    <span class="font-semibold text-gray-700">Sunday:</span>
                    <span class="text-wmsu-red font-medium"><?php echo getContent($conn, 'index', 'operating_hours_sunday', 'Closed (Emergency services available)'); ?></span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Contact Information -->
            <div class="animate-slide-up animation-delay-200">
              <div class="bg-white rounded-2xl shadow-xl overflow-hidden hover:shadow-2xl transition-shadow duration-300">
                <div class="bg-gradient-to-r from-wmsu-red to-red-700 text-white p-6">
                  <div class="flex items-center space-x-3">
                    <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center">
                      <i class="fas fa-phone-alt text-xl"></i>
                    </div>
                    <h3 class="font-cinzel text-2xl font-semibold">Contact Us</h3>
                  </div>
                </div>
                <div class="p-6 space-y-6">
                  <!-- Telephone -->
                  <div class="flex items-start space-x-4 group cursor-pointer" onclick="copyToClipboard('<?php echo $contactTelephone; ?>')">
                    <div class="w-12 h-12 bg-wmsu-red/10 rounded-full flex items-center justify-center group-hover:bg-wmsu-red/20 transition-colors duration-300">
                      <i class="fas fa-phone-alt text-wmsu-red group-hover:scale-110 transition-transform duration-300"></i>
                    </div>
                    <div class="flex-1">
                      <h4 class="font-semibold text-gray-800 mb-1">Telephone</h4>
                      <p class="text-wmsu-red font-medium hover:text-red-700 transition-colors duration-300">
                        <?php echo $contactTelephone; ?>
                      </p>
                    </div>
                    <i class="fas fa-copy text-gray-400 group-hover:text-wmsu-red transition-colors duration-300"></i>
                  </div>
                  
                  <!-- Email -->
                  <div class="flex items-start space-x-4 group cursor-pointer" onclick="copyToClipboard('<?php echo $contactEmail; ?>')">
                    <div class="w-12 h-12 bg-wmsu-red/10 rounded-full flex items-center justify-center group-hover:bg-wmsu-red/20 transition-colors duration-300">
                      <i class="fas fa-envelope text-wmsu-red group-hover:scale-110 transition-transform duration-300"></i>
                    </div>
                    <div class="flex-1">
                      <h4 class="font-semibold text-gray-800 mb-1">Email</h4>
                      <p class="text-wmsu-red font-medium hover:text-red-700 transition-colors duration-300">
                        <?php echo $contactEmail; ?>
                      </p>
                    </div>
                    <i class="fas fa-copy text-gray-400 group-hover:text-wmsu-red transition-colors duration-300"></i>
                  </div>
                  
                  <!-- Location -->
                  <div class="flex items-start space-x-4 group cursor-pointer" onclick="openMaps()">
                    <div class="w-12 h-12 bg-wmsu-red/10 rounded-full flex items-center justify-center group-hover:bg-wmsu-red/20 transition-colors duration-300">
                      <i class="fas fa-map-marker-alt text-wmsu-red group-hover:scale-110 transition-transform duration-300"></i>
                    </div>
                    <div class="flex-1">
                      <h4 class="font-semibold text-gray-800 mb-1">Location</h4>
                      <p class="text-wmsu-red font-medium hover:text-red-700 transition-colors duration-300">
                        <?php echo $contactLocation; ?>
                      </p>
                    </div>
                    <i class="fas fa-external-link-alt text-gray-400 group-hover:text-wmsu-red transition-colors duration-300"></i>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Footer -->
      <footer class="bg-wmsu-dark text-white py-12">
        <div class="container mx-auto px-4 text-center">
          <div class="flex items-center justify-center space-x-4 mb-6">
            <img src="<?php echo htmlspecialchars($logoImage['image_path']); ?>" 
                 alt="<?php echo htmlspecialchars($logoImage['image_alt']); ?>"
                 class="w-12 h-12 object-contain">
            <span class="font-cinzel text-2xl font-bold text-wmsu-gold">WMSU Health Services</span>
          </div>
          <p class="text-gray-300 text-sm leading-relaxed max-w-2xl mx-auto">
            <?php echo $footerText; ?>
          </p>
          
          <!-- Social Links -->
          <div class="flex justify-center space-x-6 mt-8">
            <a href="#" class="w-10 h-10 bg-wmsu-red rounded-full flex items-center justify-center hover:bg-red-700 transition-colors duration-300 group">
              <i class="fab fa-facebook-f text-white group-hover:scale-110 transition-transform duration-300"></i>
            </a>
            <a href="#" class="w-10 h-10 bg-wmsu-red rounded-full flex items-center justify-center hover:bg-red-700 transition-colors duration-300 group">
              <i class="fab fa-twitter text-white group-hover:scale-110 transition-transform duration-300"></i>
            </a>
            <a href="#" class="w-10 h-10 bg-wmsu-red rounded-full flex items-center justify-center hover:bg-red-700 transition-colors duration-300 group">
              <i class="fab fa-instagram text-white group-hover:scale-110 transition-transform duration-300"></i>
            </a>
            <a href="#" class="w-10 h-10 bg-wmsu-red rounded-full flex items-center justify-center hover:bg-red-700 transition-colors duration-300 group">
              <i class="fas fa-envelope text-white group-hover:scale-110 transition-transform duration-300"></i>
            </a>
          </div>
        </div>
      </footer>
    </main>
  </div>

  <!-- Service Details Modal -->
  <div id="serviceModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden">
    <div class="flex items-center justify-center min-h-screen p-4">
      <div class="bg-white rounded-2xl max-w-md w-full p-6 relative animate-fade-in">
        <button onclick="closeServiceModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 transition-colors duration-300">
          <i class="fas fa-times text-xl"></i>
        </button>
        <div id="modalContent" class="text-center">
          <!-- Content will be populated by JavaScript -->
        </div>
      </div>
    </div>
  </div>

  <!-- Notification Toast -->
  <div id="toast" class="fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50 transform translate-x-full transition-transform duration-300">
    <div class="flex items-center space-x-2">
      <i class="fas fa-check-circle"></i>
      <span id="toastMessage">Copied to clipboard!</span>
    </div>
  </div>

  <!-- JavaScript -->
  <script>
    // Smooth scrolling functions
    function scrollToSection(sectionId) {
      const element = document.getElementById(sectionId);
      if (element) {
        element.scrollIntoView({ behavior: 'smooth' });
      }
    }

    function scrollToTop() {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    // Service modal functions
    function showServiceDetails(title, description) {
      const modal = document.getElementById('serviceModal');
      const content = document.getElementById('modalContent');
      
      content.innerHTML = `
        <div class="w-20 h-20 bg-wmsu-red/10 rounded-full flex items-center justify-center mx-auto mb-6">
          <i class="fas fa-stethoscope text-3xl text-wmsu-red"></i>
        </div>
        <h3 class="font-cinzel text-2xl font-semibold text-wmsu-red mb-4">${title}</h3>
        <p class="text-gray-600 leading-relaxed">${description}</p>
        <button onclick="closeServiceModal()" class="mt-6 bg-wmsu-red text-white px-6 py-2 rounded-full hover:bg-red-700 transition-colors duration-300">
          Close
        </button>
      `;
      
      modal.classList.remove('hidden');
    }

    function closeServiceModal() {
      document.getElementById('serviceModal').classList.add('hidden');
    }

    // Clipboard functions
    function copyToClipboard(text) {
      navigator.clipboard.writeText(text).then(() => {
        showToast('Copied to clipboard!');
      }).catch(() => {
        showToast('Failed to copy');
      });
    }

    function showToast(message) {
      const toast = document.getElementById('toast');
      const toastMessage = document.getElementById('toastMessage');
      
      toastMessage.textContent = message;
      toast.classList.remove('translate-x-full');
      
      setTimeout(() => {
        toast.classList.add('translate-x-full');
      }, 3000);
    }

    // Maps function
    function openMaps() {
      const address = encodeURIComponent('<?php echo $contactLocation; ?>');
      window.open(`https://www.google.com/maps/search/?api=1&query=${address}`, '_blank');
    }

    // Intersection Observer for animations
    const observerOptions = {
      threshold: 0.1,
      rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('animate-fade-in');
        }
      });
    }, observerOptions);

    // Observe elements for animation
    document.addEventListener('DOMContentLoaded', () => {
      const animatedElements = document.querySelectorAll('.animate-slide-up');
      animatedElements.forEach(el => observer.observe(el));
      
      // Close modal when clicking outside
      document.getElementById('serviceModal').addEventListener('click', (e) => {
        if (e.target.id === 'serviceModal') {
          closeServiceModal();
        }
      });
    });

    // Parallax effect for hero section
    window.addEventListener('scroll', () => {
      const scrolled = window.pageYOffset;
      const hero = document.querySelector('.hero-section');
      if (hero) {
        const rate = scrolled * -0.5;
        hero.style.transform = `translateY(${rate}px)`;
      }
    });
  </script>
</body>
</html>
