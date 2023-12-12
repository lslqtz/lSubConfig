<?php
ini_set('user_agent', 'lSubConfig/1.0');
ini_set('default_socket_timeout', '30');
define('SubscribeKey', array('DefaultSubscribeKey'));
define('SubscribeBaseRule', 'clash_baserule.yml');
define('SubscribeBaseRuleProxiesTag', '----lPROXIES----');
define('SubscribeBaseRuleProxiesNameTag', '----lPROXIESNAME----');
define('SubscribeBaseRuleProxiesNameLowLatencyTag', '----lPROXIESNAME_LOWLATENCY----');
define('SubscribeBaseRuleProxiesNameLowLatencyMatchList', array('🇭🇰', 'HK', '香港', '🇹🇼', 'TW', '台湾', '🇯🇵', 'JP', '日本', '🇰🇷', 'KR', '韩国'));
define('SubscribeBaseRuleProxiesNameCNTag', '----lPROXIESNAME_CN----');
define('SubscribeCache', 3600); // Seconds or null.
define('SubscribeIgnoreKeyword', array('套餐', '到期', '流量', '重置'));
define('SubscribeUserInfoReturn', true);
define('SubscribeUserInfoReturnAll', false);
define('DefaultFlag', 'clash');
define('SupportFlag', array('clash', 'meta', 'stash'));
define('RewriteFlag', array('stash' => 'meta')); // Support first before rewriting.
define('RecognizeFlag', array_merge(SupportFlag, array('surge', 'sing-box', 'shadowrocket')));
define('SubscribeURL', array('https://example.com/api/v1/client/subscribe?token=a1b2c3d4e5f6g7h8i9')); // URL or Filename.
function ParseDomain(string $url): string {
	$parseURL = parse_url(trim($url));
	return trim((isset($parseURL['host']) ? $parseURL['host'] : array_shift(explode('/', $parseURL['path'], 2))));
}
function GetHTTPHeader(string $name): ?string {
	global $http_response_header;
	if (isset($http_response_header)) {
		foreach ($http_response_header as $header) {
			$headerArr = explode(': ', $header);
			if (trim(strtolower($headerArr[0])) === $name) {
				return trim($header);
			}
		}
	}
	return null;
}
header('Content-Type: text/plain; charset=UTF-8');
if (PHP_SAPI !== 'cli') {
	if (!isset($_GET['k']) || !in_array(trim($_GET['k']), SubscribeKey)) {
		http_response_code(404);
		die("File not found.\n");
	}
}
$reqFlag = ((!empty($_GET['flag'])) ? trim(strtolower($_GET['flag'])) : DefaultFlag);
if (!in_array($reqFlag, RecognizeFlag)) {
	// 不认识的 flag, 直接拒绝响应.
	http_response_code(403);
	die("Bad flag.\n");
}
$useReqFlag = ((isset(RewriteFlag[$reqFlag])) ? RewriteFlag[$reqFlag] : $reqFlag);
$subscribeBaseRule = @file_get_contents(SubscribeBaseRule);
$proxies = '';
$proxiesName = array();
$proxiesNameLowLatency = array();
$proxiesNameCN = array();
$subscribeURLCount = (count(SubscribeURL));
header('Content-Disposition: attachment; filename=Subscribe');
header('profile-update-interval: 12');
foreach (SubscribeURL as $subscribeURL) {
	$ruleSpaceIndent = 0;
	$detectProxies = -2; // -2: 等待检测代理标志, -1: 正在检测代理标志, 0: 已检测并提取代理.
	if (stripos($subscribeURL, 'flag=') === false) {
		$subscribeURL .= "&flag={$useReqFlag}";
	}
	$canCache = false;
	$useCache = false;
	if (SubscribeCache !== null) {
		if (is_dir('SubscribeCache') || mkdir('SubscribeCache')) {
			$canCache = true;
		}
		$subscribeURLDomain = str_replace('.', '-', ParseDomain($subscribeURL));
		$subscribeURLSHA1 = sha1($subscribeURL);
		if (!empty($subscribeURLDomain) && !empty($subscribeURLSHA1)) {
			$subscribeCacheFilename = "SubscribeCache/{$useReqFlag}_{$subscribeURLDomain}_{$subscribeURLSHA1}.txt";
			if (is_file($subscribeCacheFilename) && ($cacheMTime = filemtime($subscribeCacheFilename)) !== false) {
				if (($cacheMTime + SubscribeCache) < time()) {
					unlink($subscribeCacheFilename);
				} else {
					$useCache = true;
					$subscribeURL = $subscribeCacheFilename;
				}
			}
		}
	}
	$subscribeURLContent = @file($subscribeURL);
	if (empty($subscribeURLContent)) {
		continue;
	}
	if (!$useCache) {
		$subscribeUserInfo = GetHTTPHeader('subscription-userinfo');
		if (SubscribeUserInfoReturn && !empty($subscribeUserInfo) && (SubscribeUserInfoReturnAll || $subscribeURLCount === 1)) {
			header($subscribeUserInfo);
		}
		if ($canCache) {
			if (!empty($subscribeUserInfo)) {
				array_unshift($subscribeURLContent, "{$subscribeUserInfo}\n");
			}
			file_put_contents($subscribeCacheFilename, $subscribeURLContent);
		}
	}
	if (!in_array($reqFlag, SupportFlag)) {
		// 不支持的 flag, 直接转发任一原始响应.
		die(implode($subscribeURLContent));
	}
	$firstLine = true;
	foreach ($subscribeURLContent as $subscribeLine) {
		if (empty($subscribeLine)) {
			continue;
		}
		if (SubscribeUserInfoReturn && $firstLine && $useCache && (SubscribeUserInfoReturnAll || $subscribeURLCount === 1) && ($subscribeUserInfo = stristr($subscribeLine, 'subscription-userinfo')) !== false) {
			header($subscribeUserInfo);
			continue;
		}
		$firstLine = false;
		if ($ruleSpaceIndent === 0 && (($spaceIndent = strspn($subscribeLine, ' ', 0, 8)) > 0) && ($spaceIndent % 2) === 0) {
			$ruleSpaceIndent = $spaceIndent;
			$ruleSpaceIndentStr = str_repeat(' ', $ruleSpaceIndent);
		}
		if ($detectProxies === -2 && stripos($subscribeLine, 'proxies') === 0) {
			// 抓住代理节点标志!
			$detectProxies = -1;
		} else if ($detectProxies === -1) {
			if (stripos($subscribeLine, $ruleSpaceIndentStr) === 0) {
				if (preg_match('/^.*?name:(.*?),/', $subscribeLine, $proxiesNameMatches) !== false && count($proxiesNameMatches) > 1) {
					if ($reqFlag === 'stash') {
						if (stripos($subscribeLine, 'xtls-rprx-vision') !== false) {
							continue;
						}
						$subscribeLine = preg_replace('/password: ?[\'"]?(.*?)[\'"]?([, }])/', "password: '$1', auth: '$1'$2", $subscribeLine, 1);
					}
					$tmpProxiesName = trim($proxiesNameMatches[1], ",' ");
					foreach (SubscribeIgnoreKeyword as $subscribeIgnoreKeyword) {
						if (stripos($tmpProxiesName, $subscribeIgnoreKeyword) !== false) {
							continue 2;
						}
					}
					$subscribeLine = trim($subscribeLine);
					//$subscribeLine = preg_replace('/flow: ?xtls-rprx-vision,? ?/', '', $subscribeLine, 1);
					//$subscribeLine = str_replace('xtls-rprx-vision', 'xtls-rprx-origin', $subscribeLine);
					if (stripos($tmpProxiesName, '🇨🇳') === false && stripos($tmpProxiesName, 'CN') === false && stripos($tmpProxiesName, '中国') === false) {
						$proxiesName[] = "'{$tmpProxiesName}'";
						if ($reqFlag === 'stash' && stripos($subscribeLine, 'benchmark-url:') === false && stripos($subscribeLine, 'benchmark-timeout:') === false ) {
							$subscribeLine = preg_replace('/ ?} *$/', ", benchmark-timeout: 5, benchmark-url: 'http://www.gstatic.com/generate_204' }", $subscribeLine, 1);
						}
						foreach (SubscribeBaseRuleProxiesNameLowLatencyMatchList as $lowLatencyMatch) {
							if (stripos($tmpProxiesName, $lowLatencyMatch) !== false) {
								$proxiesNameLowLatency[] = "'{$tmpProxiesName}'";
								break;
							}
						}
					} else {
						$proxiesNameCN[] = "'{$tmpProxiesName}'";
						if ($reqFlag === 'stash' && stripos($subscribeLine, 'benchmark-url:') === false && stripos($subscribeLine, 'benchmark-timeout:') === false ) {
							$subscribeLine = preg_replace('/ ?} *$/', ", benchmark-timeout: 5, benchmark-url: 'http://baidu.com' }", $subscribeLine, 1);
						}
					}
					$proxies .= '    ' . $subscribeLine . "\n";
				}
			} else if (stripos($subscribeLine, ':') !== false) {
				$detectProxies = 0;
			}
		}
	}
}
$proxies = trim($proxies);
if (empty($proxies)) {
	die();
}
$proxiesNameStr = implode(', ', $proxiesName);
$proxiesNameLowLatencyStr = implode(', ', $proxiesNameLowLatency);
$proxiesNameCNStr = implode(', ', $proxiesNameCN);
if (empty($proxiesNameStr)) {
	$subscribeBaseRule = preg_replace('/, ?' . SubscribeBaseRuleProxiesNameTag . '/', '', $subscribeBaseRule);
}
if (empty($proxiesNameLowLatencyStr)) {
	$subscribeBaseRule = preg_replace('/, ?' . SubscribeBaseRuleProxiesNameLowLatencyTag . '/', '', $subscribeBaseRule);
}
if (empty($proxiesNameCNStr)) {
	$subscribeBaseRule = preg_replace('/, ?' . SubscribeBaseRuleProxiesNameCNTag . '/', '', $subscribeBaseRule);
}
echo str_replace(array(SubscribeBaseRuleProxiesTag, SubscribeBaseRuleProxiesNameTag, SubscribeBaseRuleProxiesNameLowLatencyTag, SubscribeBaseRuleProxiesNameCNTag), array($proxies, $proxiesNameStr, $proxiesNameLowLatencyStr, $proxiesNameCNStr), $subscribeBaseRule);
?>
