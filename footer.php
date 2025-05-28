
   </main>

 
   </main>

<footer class="site-footer">
    <div class="container footer-container">
        <div class="footer-section about">
            <h4>About <?php echo htmlspecialchars(SITE_NAME); ?></h4> 
            <p>Your partner in health and wellness. High-quality products delivered to your doorstep.</p>
         
             <div class="social-links">
          
                 <a href="https://www.facebook.com" title="Facebook" target="_blank" rel="noopener noreferrer"><i class="fab fa-facebook-f"></i></a>
                 <a href="https://www.instagram.com" title="Instagram" target="_blank" rel="noopener noreferrer"><i class="fab fa-instagram"></i></a>
                 <a href="https://twitter.com" title="Twitter" target="_blank" rel="noopener noreferrer"><i class="fab fa-twitter"></i></a>
          
            </div>
        </div>
        <div class="footer-section links">
        <h4>Quick Links</h4>
<ul>
    <li>
        <a href="<?php echo BASE_URL; ?>about.php">
            <i class="fas fa-info-circle fa-fw"></i> About Us
        </a>
    </li>
    <li>
        <a href="<?php echo BASE_URL; ?>contact.php">
            <i class="fas fa-envelope fa-fw"></i> Contact Us
        </a>
    </li>
    <li>
        <a href="<?php echo BASE_URL; ?>products.php">
            <i class="fas fa-shopping-cart fa-fw"></i> Shop
        </a>
    </li>
    <li>
        <a href="<?php echo BASE_URL; ?>privacy.php">
            <i class="fas fa-shield-alt fa-fw"></i> Privacy Policy
        </a>
    </li>
    <li>
        <a href="<?php echo BASE_URL; ?>terms.php">
            <i class="fas fa-file-contract fa-fw"></i> Terms of Service
        </a>
    </li>
    <!-- Example for potential future links -->
    <!--
    <li>
        <a href="<?php echo BASE_URL; ?>faq.php">
            <i class="fas fa-question-circle fa-fw"></i> FAQ
        </a>
    </li>
    <li>
        <a href="<?php echo BASE_URL; ?>returns.php">
            <i class="fas fa-undo fa-fw"></i> Returns Policy
        </a>
    </li>
    -->
</ul>
        </div>
        <div class="footer-section contact-info">
            <h4>Contact Us</h4>
            <p><i class="fas fa-map-marker-alt"></i> 143 alfa Philippines</p>
            <p><i class="fas fa-phone"></i> <a href="tel:+639927235793">+63 992 723 5793</a></p> 
            <p><i class="fas fa-envelope"></i> <a href="mailto:wealthys.system@gmail.com">wealthys.system@gmail.com</a></p> 
        </div>

       
        <div class="footer-section newsletter">
            <h4>Stay Updated!</h4>
            <p>Subscribe for the latest products & offers.</p> 
            <form action="<?php echo BASE_URL; ?>subscribe_newsletter.php" method="post" class="newsletter-form-footer"> <!-- Added class for specific styling -->
                <input type="email" name="email" placeholder="Enter your email" required>
                <?php
                
                if (function_exists('generateCsrfToken')) {
                    
                    echo '<input type="hidden" name="csrf_token" value="' . generateCsrfToken() . '">';
                    // }
                }
                ?>
                <button type="submit" class="btn btn-sm">Subscribe</button> 
            </form>
          
             <?php
            
             if (function_exists('display_message')) {
                 display_message(); 
             }
             ?>
        </div>
       

    </div> 

    <div class="footer-bottom">
        <p>© <?php echo date("Y"); ?> <?php echo htmlspecialchars(SITE_NAME); ?>. All Rights Reserved.</p> 
    </div>
</footer>


    <script src="<?php echo BASE_URL; ?>js/script.js?v=<?php echo time();  ?>"></script>

</body>
</html>
<?php

?>