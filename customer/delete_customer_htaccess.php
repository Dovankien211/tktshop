<?php
/**
 * Delete problematic customer/.htaccess file
 * Save as /tktshop/delete_customer_htaccess.php
 */

echo "<h1>🔧 DELETE Customer .htaccess File</h1>";

$htaccess_customer = $_SERVER['DOCUMENT_ROOT'] . '/tktshop/customer/.htaccess';

echo "<div style='background: #ffe8e8; padding: 15px; border: 1px solid #f44336;'>";
echo "<strong>TARGET FILE:</strong> <code>$htaccess_customer</code><br>";

if (file_exists($htaccess_customer)) {
    echo "<strong>Status:</strong> ❌ File EXISTS (causing problems)<br>";
    echo "<strong>Size:</strong> " . filesize($htaccess_customer) . " bytes<br>";
    
    // Show current content
    echo "<br><strong>Current problematic content:</strong><br>";
    echo "<pre style='background: #f8f9fa; padding: 10px; border: 1px solid #ddd; max-height: 200px; overflow-y: auto; font-size: 12px;'>";
    echo htmlspecialchars(file_get_contents($htaccess_customer));
    echo "</pre>";
    
    // Delete the file
    if (unlink($htaccess_customer)) {
        echo "<div style='background: #e8f5e8; padding: 15px; border: 1px solid #4caf50; margin-top: 15px;'>";
        echo "<strong>✅ SUCCESS!</strong><br>";
        echo "File has been DELETED successfully.<br>";
        echo "All routing will now be handled by parent /tktshop/.htaccess";
        echo "</div>";
    } else {
        echo "<div style='background: #ffe8e8; padding: 15px; border: 1px solid #f44336; margin-top: 15px;'>";
        echo "<strong>❌ FAILED!</strong><br>";
        echo "Could not delete file. Please delete manually:<br>";
        echo "<code>Delete: $htaccess_customer</code>";
        echo "</div>";
    }
    
} else {
    echo "<strong>Status:</strong> ✅ File NOT EXISTS (good!)<br>";
    echo "No action needed.";
}
echo "</div>";

echo "<h2>🔧 Verification</h2>";

if (!file_exists($htaccess_customer)) {
    echo "<div style='background: #e8f5e8; padding: 15px; border: 1px solid #4caf50;'>";
    echo "<strong>✅ VERIFICATION PASSED!</strong><br>";
    echo "Customer .htaccess file is now gone.<br>";
    echo "All routing handled by parent .htaccess.<br>";
    echo "<br>";
    echo "<strong>NOW TEST:</strong><br>";
    echo "1. <a href='/tktshop/customer/products_fixed.php' target='_blank'>products_fixed.php</a> → should work<br>";
    echo "2. Click 'Xem chi tiết' on 'taats' → should go to product_detail.php?slug=taats<br>";
    echo "3. <a href='/tktshop/customer/cart_fixed.php' target='_blank'>cart_fixed.php</a> → should work<br>";
    echo "4. Click 'Thanh toán' → should go to checkout.php<br>";
    echo "</div>";
} else {
    echo "<div style='background: #ffe8e8; padding: 15px; border: 1px solid #f44336;'>";
    echo "<strong>❌ VERIFICATION FAILED!</strong><br>";
    echo "File still exists. Please delete manually.";
    echo "</div>";
}

echo "<h2>🔧 Manual Delete Instructions</h2>";
echo "<div style='background: #f0f8ff; padding: 15px; border: 1px solid #007bff;'>";
echo "<strong>If script cannot delete, do this manually:</strong><br>";
echo "1. Open File Manager or FTP<br>";
echo "2. Navigate to: <code>/tktshop/customer/</code><br>";
echo "3. Find file: <code>.htaccess</code><br>";
echo "4. DELETE the file completely<br>";
echo "5. Refresh and test again<br>";
echo "</div>";

echo "<h2>🔧 Alternative: Replace with Empty Content</h2>";
echo "<div style='background: #f8f9fa; padding: 15px; border: 1px solid #333;'>";
echo "<strong>If you cannot delete, replace content with:</strong><br>";
echo "<pre style='background: #e8f5e8; padding: 10px; border: 1px solid #4caf50;'>";
echo htmlspecialchars('# Empty .htaccess - No rules
# All routing handled by parent .htaccess

DirectoryIndex index.php');
echo "</pre>";
echo "</div>";
?>