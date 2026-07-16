<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RAM-YUM STORE - Login</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <link rel="stylesheet" href="login.css">
</head>
<body>
    <div class="intro-screen" id="introScreen">
        <div class="intro-bg"></div>
        <div class="intro-content">
            <div class="intro-logo-wrap">
                <img src="assets/logo.png" alt="RAM-YUM Logo" class="intro-logo">
                <div class="steam steam1"></div>
                <div class="steam steam2"></div>
                <div class="steam steam3"></div>
            </div>
            <h1 class="intro-title">
                <span style="--i:0">R</span><span style="--i:1">A</span><span style="--i:2">M</span><span style="--i:3">-</span><span style="--i:4">Y</span><span style="--i:5">U</span><span style="--i:6">M</span>
            </h1>
            <p class="intro-subtitle">KOREAN &amp; JAPANESE STORE</p>
            <div class="intro-loader">
                <div class="intro-loader-bar"></div>
            </div>
            <p class="intro-tap">Welcome to our SSMPS</p>
        </div>
    </div>

    <div class="main-container" id="mainContainer">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="header text-center mb-5">
                        <img src="assets/logo.png" alt="RAM-YUM Logo" class="logo-circle">
                        <div class="ms-3 text-start">
                            <h1 class="ram-title">RAM-YUM</h1>
                            <p class="subtitle">KOREAN &amp; JAPANESE STORE</p>
                        </div>
                    </div>

                            <div class="login-card">
                                <h2 class="log-tit">LOGIN</h2>
                                
                                <form id="loginForm">
                                    <div class="mb-4">
                                        <input type="email" class="form-control1" id="email" placeholder=" " required>
                                        <label for="email" class="form-label1">Email Address</label>
                                        <div class="invalid-feedback" id="emailError"></div>
                                    </div>
                                    
                                    <div class="mb-4 position-relative">
                                        <input type="password" class="form-control2" id="password" placeholder=" " required>
                                        <label for="password" class="form-label2">Password</label>
                                        <button type="button" class="eye-pass" id="togglePassword">
                                            <i class="fas fa-eye" id="eyeIcon"></i>
                                        </button>
                                        <div class="invalid-feedback" id="passwordError"></div>
                                    </div>
                                    
                                    <div class="rem-forg">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="rememberMe">
                                            <label class="text-forg" for="rememberMe">Remember me</label>
                                        </div>
                                        <a href="#" class="text-forg">Forgot Password?</a>
                                    </div>
                                    
                                    <button type="submit" class="btn-login" id="loginBtn">
                                        <i class="fas fa-sign-in-alt me-2"></i>LOGIN
                                        <div class="logd"></div>
                                    </button>
                                </form>
                                
                                <div class="footer-text">
                                    <small>--- Account denied? <a href="#" id="contactAdmin">Contact Admin Here</a> ---</small>
                                </div>
                            </div>
                        <div class="bg">
                             <img src="assets/bg1.png" alt="Background Image" class="bg-image">
                        </div>
                        <div class="bg-logo-cont">
                            <img src="assets/bg-logo.png" alt="Ramen Bowl" class="ramen-illustration">
                        </div>
                        <div class="dsg">
                            <img src="assets/design.png" alt="Design Element" class="dsg-image">
                        </div>
                </div>
            </div>
        </div>
    </div>

    <script src="intro.js"></script>
    <script src="login.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>