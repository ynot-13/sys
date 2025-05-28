<?php

$page_title = "Terms of Service"; 



require_once 'includes/config.php'; 
require_once 'includes/functions.php';

require_once 'includes/header.php';

if (!defined('SITE_NAME')) {
    define('SITE_NAME', 'Your Website Name'); 
}

$jurisdiction = " Philippines"; 
$last_updated_date = "Date Last Updated, April 28, 2025"; 

?>

<div class="container main-content">
    <div class="privacy-policy-page"> 
        <h1>Terms of Service</h1>
        <p class="last-updated">Last Updated: <?php echo htmlspecialchars($last_updated_date); ?></p>

        <p>Welcome to <?php echo htmlspecialchars(SITE_NAME); ?>! These Terms of Service ("Terms", "Terms of Service") govern your use of our website located at <?php echo htmlspecialchars(BASE_URL); ?> (together or individually "Service") operated by <?php echo htmlspecialchars(SITE_NAME); ?>.</p>

        <p>Please read these Terms of Service carefully before using our Service.</p>

        <p>Your access to and use of the Service is conditioned upon your acceptance of and compliance with these Terms. These Terms apply to all visitors, users, and others who wish to access or use the Service.</p>

        <p>By accessing or using the Service, you agree to be bound by these Terms. If you disagree with any part of the terms, then you do not have permission to access the Service.</p>

        <hr>

        <h2> Use of Our Service</h2>
        <p>
            <strong>Eligibility:</strong> You must be at least [Minimum Age, 18] years old to use our Service. By agreeing to these Terms, you represent and warrant to us that you are at least [18 Age] years old.
        </p>
        <p>
            <strong>Accounts:</strong> When you create an account with us, you guarantee that the information you provide is accurate, complete, and current at all times. Inaccurate, incomplete, or obsolete information may result in the immediate termination of your account on the Service. You are responsible for maintaining the confidentiality of your account and password.
        </p>
        <p>
            <strong>Prohibited Conduct:</strong> You agree not to use the Service for any unlawful purpose or any purpose prohibited under this clause. You agree not to use the Service in any way that could damage the Service, the business of <?php echo htmlspecialchars(SITE_NAME); ?>, or other users.
        </p>

        <hr>

        <h2> Purchases and Payments (If Applicable)</h2>
        <p>
            If you wish to purchase any product or service made available through the Service ("Purchase"), you may be asked to supply certain information relevant to your Purchase including, without limitation, your credit card number, the expiration date of your credit card, your billing address, and your shipping information.
        </p>
        <p>
             We reserve the right to refuse or cancel your order at any time for reasons including but not limited to: product or service availability, errors in the description or price of the product or service, error in your order, or other reasons.
        </p>

        <hr>

        <h2> Intellectual Property</h2>
        <p>
            The Service and its original content (excluding Content provided by users), features, and functionality are and will remain the exclusive property of <?php echo htmlspecialchars(SITE_NAME); ?> and its licensors. The Service is protected by copyright, trademark, and other laws of both <?php echo htmlspecialchars($jurisdiction); ?> and foreign countries. Our trademarks and trade dress may not be used in connection with any product or service without the prior written consent of <?php echo htmlspecialchars(SITE_NAME); ?>.
        </p>
        <p>
            <strong>[If users can submit content (e.g., reviews, comments):]</strong> You retain any and all of your rights to any Content you submit, post or display on or through the Service and you are responsible for protecting those rights. By submitting Content, you grant <?php echo htmlspecialchars(SITE_NAME); ?> a worldwide, non-exclusive, royalty-free license to use, copy, reproduce, process, adapt, modify, publish, transmit, display and distribute such Content.
        </p>

        <hr>

        <h2> Links To Other Web Sites</h2>
        <p>
            Our Service may contain links to third-party web sites or services that are not owned or controlled by <?php echo htmlspecialchars(SITE_NAME); ?>.
        </p>
        <p>
            <?php echo htmlspecialchars(SITE_NAME); ?> has no control over, and assumes no responsibility for the content, privacy policies, or practices of any third-party web sites or services. We do not warrant the offerings of any of these entities/individuals or their websites. You acknowledge and agree that <?php echo htmlspecialchars(SITE_NAME); ?> shall not be responsible or liable, directly or indirectly, for any damage or loss caused or alleged to be caused by or in connection with use of or reliance on any such content, goods or services available on or through any such third-party web sites or services.
        </p>
        <p>
            We strongly advise you to read the terms and conditions and privacy policies of any third-party web sites or services that you visit.
        </p>

        <hr>

        <h2> Termination</h2>
        <p>
            We may terminate or suspend your account and bar access to the Service immediately, without prior notice or liability, under our sole discretion, for any reason whatsoever and without limitation, including but not limited to a breach of the Terms.
        </p>
        <p>
            If you wish to terminate your account, you may simply discontinue using the Service. [Add details if there's a specific account closure procedure.]
        </p>
        <p>
            All provisions of the Terms which by their nature should survive termination shall survive termination, including, without limitation, ownership provisions, warranty disclaimers, indemnity, and limitations of liability.
        </p>

        <hr>

        <h2> Disclaimer of Warranties; Limitation of Liability</h2>
        <p>
            The Service is provided on an "AS IS" and "AS AVAILABLE" basis. <?php echo htmlspecialchars(SITE_NAME); ?> makes no representations or warranties of any kind, express or implied, as to the operation of their services, or the information, content or materials included therein. You expressly agree that your use of these services, their content, and any services or items obtained from us is at your sole risk.
        </p>
        <p>
            Neither <?php echo htmlspecialchars(SITE_NAME); ?> nor any person associated with <?php echo htmlspecialchars(SITE_NAME); ?> makes any warranty or representation with respect to the completeness, security, reliability, quality, accuracy, or availability of the services... [Add more specific disclaimers as relevant to your service/products.]
        </p>
         <p>
            In no event shall <?php echo htmlspecialchars(SITE_NAME); ?>, nor its directors, employees, partners, agents, suppliers, or affiliates, be liable for any indirect, incidental, special, consequential or punitive damages, including without limitation, loss of profits, data, use, goodwill, or other intangible losses, resulting from (i) your access to or use of or inability to access or use the Service; (ii) any conduct or content of any third party on the Service; (iii) any content obtained from the Service; and (iv) unauthorized access, use or alteration of your transmissions or content, whether based on warranty, contract, tort (including negligence) or any other legal theory, whether or not we have been informed of the possibility of such damage, and even if a remedy set forth herein is found to have failed of its essential purpose. [LIMITATIONS MAY VARY BY JURISDICTION - LEGAL REVIEW IS CRITICAL HERE].
        </p>

        <hr>

        <h2> Governing Law</h2>
        <p>
            These Terms shall be governed and construed in accordance with the laws of <?php echo htmlspecialchars($jurisdiction); ?>, without regard to its conflict of law provisions.
        </p>
        <p>
            Our failure to enforce any right or provision of these Terms will not be considered a waiver of those rights. If any provision of these Terms is held to be invalid or unenforceable by a court, the remaining provisions of these Terms will remain in effect. These Terms constitute the entire agreement between us regarding our Service, and supersede and replace any prior agreements we might have had between us regarding the Service.
        </p>

        <hr>

        <h2> Changes to Terms of Service</h2>
        <p>
            We reserve the right, at our sole discretion, to modify or replace these Terms at any time. If a revision is material we will provide at least [Number] days' notice (e.g., 30 days) prior to any new terms taking effect. What constitutes a material change will be determined at our sole discretion.
        </p>
        <p>
            By continuing to access or use our Service after any revisions become effective, you agree to be bound by the revised terms. If you do not agree to the new terms, you are no longer authorized to use the Service.
        </p>

        <hr>

        <h2> Contact Us</h2>
        <p>
            If you have any questions about these Terms, please contact us:
        </p>
        <ul>
            <li>By email: wealthys.system.mail@gmail.com</li>
            <li>By visiting this page on our website: <a href="<?php echo BASE_URL; ?>contact.php"><?php echo BASE_URL; ?>contact.php</a></li>
            <li>09927235793</li>
        </ul>

        

    </div> 
</div> 

<?php

require_once 'includes/footer.php';
?>