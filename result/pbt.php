<?php
function getNatTestResultLinks($targetMonth) {
    $url = "https://nat-test.jp/contents/result.html";
    $baseUrl = "https://nat-test.jp/contents/";

    // 1. Fetch HTML directly from the server (No CORS proxy needed)
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $html = curl_exec($ch);
    curl_close($ch);

    if (!$html) return [];

    // 2. Parse the HTML
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);

    // 3. Find the row for the target month
    $rows = $xpath->query("//table//tr[th[contains(text(), '$targetMonth')]]");
    $results = [];

    if ($rows->length > 0) {
        $row = $rows->item(0);
        $links = $xpath->query(".//a", $row);
        
        // 4. Extract and map the links
        foreach ($links as $link) {
            $href = $link->getAttribute("href");
            $text = trim($link->nodeValue);
            
            $level = $text;
            if (strpos($text, '1Q') !== false) $level = 'N1';
            if (strpos($text, '2Q') !== false) $level = 'N2';
            if (strpos($text, '3Q') !== false) $level = 'N3';
            if (strpos($text, '4Q') !== false) $level = 'N4';
            if (strpos($text, '5Q') !== false) $level = 'N5';
            
            $results[$level] = (strpos($href, 'http') === 0) ? $href : $baseUrl . $href;
        }
    }
    return $results;
}

// Execute the function
$targetMonth = "9月"; // Or "October"
$testLinks = getNatTestResultLinks($targetMonth);
?>

<!-- Now display the results in your HTML anywhere on the page -->
<!DOCTYPE html>
<html lang="en">
<head>
    <title>NAT-TEST Results</title>
</head>
<body>
    <h2>Results for <?php echo htmlspecialchars($targetMonth); ?></h2>
    
    <?php if (!empty($testLinks)): ?>
        <ul>
            <?php foreach ($testLinks as $level => $link): ?>
                <li><strong><?php echo $level; ?>:</strong> <a href="<?php echo $link; ?>" target="_blank">View Result</a></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No results found for this month.</p>
    <?php endif; ?>
</body>
</html>