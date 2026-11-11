<?php
$url = "https://nat-test.jp/contents/result.html";
$targetMonth = "10月"; // Or "October"
$baseUrl = "https://nat-test.jp/contents/";

// 1. Fetch the HTML content using cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
// Bypass SSL verification if you run into local certificate issues
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
$html = curl_exec($ch);
curl_close($ch);

if (!$html) {
    die("Failed to fetch the website content.");
}

// 2. Parse the HTML using DOMDocument
$dom = new DOMDocument();
@$dom->loadHTML($html); // '@' suppresses warnings from poorly formatted HTML
$xpath = new DOMXPath($dom);

// 3. Find the row (tr) containing the header (th) with our target month
$query = "//table//tr[th[contains(text(), '$targetMonth')]]";
$rows = $xpath->query($query);

$results = [];

if ($rows->length > 0) {
    $row = $rows->item(0);
    
    // 4. Find all links (a) within this specific row
    $links = $xpath->query(".//a", $row);
    
    foreach ($links as $link) {
        $href = $link->getAttribute("href");
        $text = trim($link->nodeValue);
        
        // Map 1Q-5Q to N1-N5
        $level = $text;
        if (strpos($text, '1Q') !== false) $level = 'N1';
        if (strpos($text, '2Q') !== false) $level = 'N2';
        if (strpos($text, '3Q') !== false) $level = 'N3';
        if (strpos($text, '4Q') !== false) $level = 'N4';
        if (strpos($text, '5Q') !== false) $level = 'N5';
        
        // Convert relative URLs to absolute URLs
        $absoluteUrl = (strpos($href, 'http') === 0) ? $href : $baseUrl . $href;
        $results[$level] = $absoluteUrl;
    }
}

// 5. Output the results
if (empty($results)) {
    echo "No results found for {$targetMonth}.\n";
} else {
    echo "<h3>Results for {$targetMonth}</h3><ul>";
    foreach ($results as $level => $link) {
        echo "<li><strong>{$level}</strong> = <a href='{$link}' target='_blank'>{$link}</a></li>";
    }
    echo "</ul>";
}
?>