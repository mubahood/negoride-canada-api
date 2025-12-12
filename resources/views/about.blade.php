<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>About - Rideshare Uganda</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-yellow: #FFC107;
            --dark-bg: #1a1a1a;
            --darker-bg: #0d0d0d;
            --light-gray: #f8f9fa;
            --text-gray: #6c757d;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Roboto, system-ui, -apple-system, BlinkMacSystemFont, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
            background-color: var(--darker-bg);
            color: #333;
            line-height: 1.6;
        }

        /* Hero Section */
        .hero-section {
            background: linear-gradient(135deg, var(--darker-bg) 0%, var(--dark-bg) 100%);
            color: white;
            padding: 100px 0 80px;
            position: relative;
            overflow: hidden;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg width="100" height="100" xmlns="http://www.w3.org/2000/svg"><circle cx="50" cy="50" r="2" fill="%23FFC107" opacity="0.1"/></svg>');
            opacity: 0.5;
        }

        .hero-content {
            position: relative;
            z-index: 1;
        }

        .hero-title {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 20px;
            color: var(--primary-yellow);
        }

        .hero-subtitle {
            font-size: 1.5rem;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 10px;
        }

        .hero-tagline {
            font-size: 1.1rem;
            color: rgba(255, 255, 255, 0.7);
        }

        /* Content Sections */
        .content-section {
            padding: 60px 0;
        }

        .section-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 15px;
            color: var(--dark-bg);
            position: relative;
            display: inline-block;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 0;
            width: 80px;
            height: 4px;
            background: var(--primary-yellow);
        }

        .section-subtitle {
            color: var(--text-gray);
            font-size: 1.1rem;
            margin-bottom: 40px;
        }

        /* Feature Cards */
        .feature-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: 2px solid transparent;
        }

        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
            border-color: var(--primary-yellow);
        }

        .feature-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, var(--primary-yellow) 0%, #FFD54F 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            font-size: 2rem;
            color: var(--dark-bg);
        }

        .feature-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 15px;
            color: var(--dark-bg);
        }

        .feature-text {
            color: var(--text-gray);
            line-height: 1.8;
        }

        /* Stats Section */
        .stats-section {
            background: linear-gradient(135deg, var(--dark-bg) 0%, var(--darker-bg) 100%);
            color: white;
            padding: 80px 0;
        }

        .stat-card {
            text-align: center;
            padding: 30px 15px;
        }

        .stat-number {
            font-size: 3rem;
            font-weight: 700;
            color: var(--primary-yellow);
            margin-bottom: 10px;
        }

        .stat-label {
            font-size: 1.1rem;
            color: rgba(255, 255, 255, 0.8);
        }

        /* Leadership Section */
        .leadership-card {
            background: white;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .leader-image {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            border: 5px solid var(--primary-yellow);
            margin: 0 auto 20px;
            background: linear-gradient(135deg, var(--primary-yellow) 0%, #FFD54F 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: var(--dark-bg);
            font-weight: 700;
        }

        .leader-name {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--dark-bg);
            margin-bottom: 10px;
        }

        .leader-title {
            color: var(--primary-yellow);
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .leader-bio {
            color: var(--text-gray);
            line-height: 1.8;
        }

        /* Contact Section */
        .contact-section {
            background: var(--light-gray);
            padding: 60px 0;
        }

        .contact-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }

        .contact-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        }

        .contact-icon {
            width: 50px;
            height: 50px;
            background: var(--primary-yellow);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            font-size: 1.3rem;
            color: var(--dark-bg);
        }

        .contact-label {
            font-weight: 600;
            color: var(--dark-bg);
            margin-bottom: 5px;
        }

        .contact-value {
            color: var(--text-gray);
        }

        .contact-value a {
            color: var(--text-gray);
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .contact-value a:hover {
            color: var(--primary-yellow);
        }

        /* Values Section */
        .values-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            margin-top: 40px;
        }

        .value-item {
            background: white;
            padding: 30px 25px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            border-left: 4px solid var(--primary-yellow);
        }

        .value-item h4 {
            color: var(--dark-bg);
            font-size: 1.3rem;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .value-item p {
            color: var(--text-gray);
            line-height: 1.7;
        }

        /* CTA Section */
        .cta-section {
            background: linear-gradient(135deg, var(--primary-yellow) 0%, #FFD54F 100%);
            padding: 80px 0;
            text-align: center;
        }

        .cta-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--dark-bg);
            margin-bottom: 20px;
        }

        .cta-text {
            font-size: 1.2rem;
            color: var(--darker-bg);
            margin-bottom: 30px;
        }

        .btn-download {
            background: var(--dark-bg);
            color: white;
            padding: 15px 40px;
            border-radius: 50px;
            font-size: 1.1rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            border: 2px solid var(--dark-bg);
        }

        .btn-download:hover {
            background: transparent;
            color: var(--dark-bg);
            text-decoration: none;
            transform: translateY(-2px);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.5rem;
            }

            .hero-subtitle {
                font-size: 1.2rem;
            }

            .section-title {
                font-size: 2rem;
            }

            .stat-number {
                font-size: 2rem;
            }
        }

        /* Background white for content sections */
        .bg-white-section {
            background: white;
        }
    </style>
</head>

<body>
    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="hero-content text-center">
                <h1 class="hero-title">Rideshare Uganda</h1>
                <p class="hero-subtitle">Connecting Riders & Drivers Across Uganda</p>
                <p class="hero-tagline">Making transportation accessible, affordable, and reliable since 2021</p>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section class="content-section bg-white-section">
        <div class="container">
            <div class="row">
                <div class="col-lg-12 text-center mb-5">
                    <h2 class="section-title">Who We Are</h2>
                    <p class="section-subtitle">Your trusted ride-sharing platform in Uganda</p>
                </div>
            </div>
            <div class="row align-items-center">
                <div class="col-lg-6 mb-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-car"></i>
                        </div>
                        <h3 class="feature-title">Our Mission</h3>
                        <p class="feature-text">
                            To revolutionize urban transportation in Uganda by providing a safe, reliable, and 
                            affordable ride-sharing platform that connects riders with drivers efficiently. 
                            We aim to reduce traffic congestion, promote sustainable transportation, and create 
                            economic opportunities for drivers across the country.
                        </p>
                    </div>
                </div>
                <div class="col-lg-6 mb-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-eye"></i>
                        </div>
                        <h3 class="feature-title">Our Vision</h3>
                        <p class="feature-text">
                            To become Uganda's leading ride-sharing platform, setting the standard for 
                            safety, reliability, and customer satisfaction. We envision a future where 
                            every Ugandan has access to convenient, affordable transportation at their 
                            fingertips, fostering community connections and economic growth.
                        </p>
                    </div>
                </div>
            </div>
            <div class="row mt-4">
                <div class="col-lg-12">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-history"></i>
                        </div>
                        <h3 class="feature-title">Our Story</h3>
                        <p class="feature-text">
                            Founded in 2021, Rideshare Uganda emerged from a vision to solve the transportation 
                            challenges faced by millions of Ugandans. We recognized the need for a locally-developed 
                            platform that understands the unique needs of our communities, supports local languages, 
                            and integrates with local payment systems.
                        </p>
                        <p class="feature-text mt-3">
                            Since our launch, we've grown to serve thousands of riders and drivers across major 
                            Ugandan cities, facilitating countless safe trips and creating meaningful employment 
                            opportunities. Our commitment to innovation, safety, and customer satisfaction has 
                            made us a trusted name in Ugandan transportation.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats-section">
        <div class="container">
            <div class="row">
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-number">4+</div>
                        <div class="stat-label">Years of Service</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-number">10K+</div>
                        <div class="stat-label">Active Users</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-number">5K+</div>
                        <div class="stat-label">Registered Drivers</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-number">100K+</div>
                        <div class="stat-label">Completed Trips</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Leadership Section -->
    <section class="content-section bg-white-section">
        <div class="container">
            <div class="row">
                <div class="col-lg-12 text-center mb-5">
                    <h2 class="section-title">Leadership</h2>
                    <p class="section-subtitle">Meet the visionary behind Rideshare Uganda</p>
                </div>
            </div>
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="leadership-card">
                        <div class="leader-image">
                            <i class="fas fa-user-tie"></i>
                        </div>
                        <h3 class="leader-name">PhD Samuel Ocen</h3>
                        <p class="leader-title">Chief Executive Officer & Founder</p>
                        <p class="leader-bio">
                            Dr. Samuel Ocen brings extensive expertise in technology and transportation to 
                            Rideshare Uganda. With a passion for innovation and community development, he 
                            founded Rideshare Uganda to address the transportation challenges faced by Ugandans 
                            while creating sustainable employment opportunities. His vision and leadership have 
                            been instrumental in establishing Rideshare Uganda as a trusted and reliable 
                            transportation platform across the country.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Values Section -->
    <section class="content-section" style="background: #f8f9fa;">
        <div class="container">
            <div class="row">
                <div class="col-lg-12 text-center mb-5">
                    <h2 class="section-title">Our Core Values</h2>
                    <p class="section-subtitle">The principles that guide everything we do</p>
                </div>
            </div>
            <div class="values-grid">
                <div class="value-item">
                    <h4><i class="fas fa-shield-alt" style="color: var(--primary-yellow);"></i> Safety First</h4>
                    <p>We prioritize the safety and security of both riders and drivers through rigorous 
                       verification processes, real-time tracking, and 24/7 support.</p>
                </div>
                <div class="value-item">
                    <h4><i class="fas fa-handshake" style="color: var(--primary-yellow);"></i> Reliability</h4>
                    <p>We're committed to providing dependable service that our users can count on, 
                       with fast response times and consistent quality.</p>
                </div>
                <div class="value-item">
                    <h4><i class="fas fa-users" style="color: var(--primary-yellow);"></i> Community</h4>
                    <p>We believe in building strong connections within our communities and supporting 
                       local economic development through job creation.</p>
                </div>
                <div class="value-item">
                    <h4><i class="fas fa-lightbulb" style="color: var(--primary-yellow);"></i> Innovation</h4>
                    <p>We continuously improve our technology and services to meet the evolving needs 
                       of our users and stay ahead in the transportation industry.</p>
                </div>
                <div class="value-item">
                    <h4><i class="fas fa-dollar-sign" style="color: var(--primary-yellow);"></i> Affordability</h4>
                    <p>We strive to keep our services accessible and affordable for all Ugandans while 
                       ensuring fair compensation for our drivers.</p>
                </div>
                <div class="value-item">
                    <h4><i class="fas fa-leaf" style="color: var(--primary-yellow);"></i> Sustainability</h4>
                    <p>We promote carpooling and efficient routing to reduce environmental impact and 
                       contribute to a greener future for Uganda.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="content-section bg-white-section">
        <div class="container">
            <div class="row">
                <div class="col-lg-12 text-center mb-5">
                    <h2 class="section-title">What We Offer</h2>
                    <p class="section-subtitle">Comprehensive features for riders and drivers</p>
                </div>
            </div>
            <div class="row">
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-map-marked-alt"></i>
                        </div>
                        <h3 class="feature-title">Real-Time Tracking</h3>
                        <p class="feature-text">
                            Track your driver's location in real-time and share your trip details with 
                            family and friends for added peace of mind.
                        </p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-mobile-alt"></i>
                        </div>
                        <h3 class="feature-title">Mobile Payments</h3>
                        <p class="feature-text">
                            Pay conveniently using mobile money or cash. We support all major Ugandan 
                            mobile money platforms for seamless transactions.
                        </p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-star"></i>
                        </div>
                        <h3 class="feature-title">Rating System</h3>
                        <p class="feature-text">
                            Rate your experience after each trip to help us maintain high service 
                            standards and build a trusted community.
                        </p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-headset"></i>
                        </div>
                        <h3 class="feature-title">24/7 Support</h3>
                        <p class="feature-text">
                            Our dedicated customer support team is available round the clock to assist 
                            you with any questions or concerns.
                        </p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-calculator"></i>
                        </div>
                        <h3 class="feature-title">Fare Estimates</h3>
                        <p class="feature-text">
                            Get transparent pricing with upfront fare estimates before you book, 
                            so you always know what to expect.
                        </p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <h3 class="feature-title">Scheduled Rides</h3>
                        <p class="feature-text">
                            Plan ahead by scheduling rides in advance for airport transfers, 
                            important meetings, or early morning commutes.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section class="contact-section">
        <div class="container">
            <div class="row">
                <div class="col-lg-12 text-center mb-5">
                    <h2 class="section-title">Get In Touch</h2>
                    <p class="section-subtitle">We're here to help and answer any questions you might have</p>
                </div>
            </div>
            <div class="row justify-content-center">
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="contact-card">
                        <div class="contact-icon">
                            <i class="fas fa-phone"></i>
                        </div>
                        <div class="contact-label">Phone</div>
                        <div class="contact-value">
                            <a href="tel:+256775679505">+256 775 679 505</a>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="contact-card">
                        <div class="contact-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="contact-label">Email</div>
                        <div class="contact-value">
                            <a href="mailto:samocenuel@gmail.com">samocenuel@gmail.com</a>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="contact-card">
                        <div class="contact-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div class="contact-label">Location</div>
                        <div class="contact-value">
                            Kampala, Uganda
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="container">
            <h2 class="cta-title">Ready to Get Started?</h2>
            <p class="cta-text">Download Rideshare Uganda today and experience the future of transportation</p>
            <a href="https://play.google.com/store" class="btn-download">
                <i class="fab fa-google-play mr-2"></i> Download on Google Play
            </a>
        </div>
    </section>

    <!-- Footer -->
    <footer style="background: var(--darker-bg); color: rgba(255,255,255,0.7); padding: 40px 0; text-align: center;">
        <div class="container">
            <p style="margin-bottom: 15px; font-size: 1.1rem;">
                <strong style="color: var(--primary-yellow);">Rideshare Uganda</strong> - Your trusted ride-sharing partner
            </p>
            <p style="margin-bottom: 10px;">
                &copy; 2021 - {{ date('Y') }} Rideshare Uganda. All rights reserved.
            </p>
            <div style="margin-top: 20px;">
                <a href="/policy" style="color: rgba(255,255,255,0.7); text-decoration: none; margin: 0 15px; transition: color 0.3s;">
                    Privacy Policy
                </a>
                <a href="/about" style="color: var(--primary-yellow); text-decoration: none; margin: 0 15px; transition: color 0.3s;">
                    About Us
                </a>
            </div>
        </div>
    </footer>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
