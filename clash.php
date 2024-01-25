<?php
ini_set('user_agent', 'lSubConfig/1.0');
ini_set('default_socket_timeout', '30');
define('SubscribeKey', array('DefaultSubscribeKey'));
define('SubscribeBaseRule', 'clash_baserule.yml');
define('SubscribeBaseRuleSpace1IndentStr', '    ');
define('SubscribeBaseRuleProxiesTag', '----lPROXIES----');
define('SubscribeBaseRuleProxiesNameTag', '----lPROXIESNAME----');
define('SubscribeBaseRuleProxiesNameTag_Auto', '----lPROXIESNAME_AUTO----'); // 默认使用符合 Keyword 要求的 LowLatency 节点作为自动节点.
define('SubscribeBaseRuleProxiesNameTag_LowLatency', '----lPROXIESNAME_LOWLATENCY----'); // 匹配以下关键词的被视作 LowLatency 节点.
define('SubscribeBaseRuleProxiesNameMatchList_LowLatency', array('🇭🇰', 'HK', '香港', '🇹🇼', 'TW', '台湾'));
define('SubscribeBaseRuleProxiesNameTag_CN', '----lPROXIESNAME_CN----');
define('SubscribeCache', 3600); // Seconds or null.
define('SubscribeUpdateInterval', 12); // Hours or null.
define('SubscribeAutoUseLowLatencyOnly', true); // 默认仅使用 LowLatency 节点作为自动节点.
define('SubscribeIgnoreKeyword_Auto', array('IPv6')); // 不使用仅 IPv6 节点作为自动节点.
define('SubscribeIgnoreKeyword', array('套餐', '到期', '流量', '重置', '官网', '最新'));
define('SubscribeUserInfoReturn', true);
define('SubscribeUserInfoReturnAll', false);
define('DefaultFlag', 'clash');
define('SupportFlag', array('clash', 'meta', 'stash'));
define('RewriteFlag', array('stash' => 'meta')); // Support first before rewriting.
define('RecognizeFlag', array_merge(SupportFlag, array('surge', 'sing-box', 'shadowrocket')));
define('SubscribeURL', array('https://example1.com/api/v1/client/subscribe?token=a1b2c3d4e5f6g7h8i9' => null, 'https://example2.com/api/v1/client/subscribe?token=a1b2c3d4e5f6g7h8i9' => '&flag={useReqFlag}')); // (URL or Filename) => (FlagParam or null).
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
function NameFilter(array $value): bool {
	if (!isset($value['name'])) {
		return false;
	}
	foreach (SubscribeIgnoreKeyword as $subscribeIgnoreKeyword) {
		if (stripos($value['name'], $subscribeIgnoreKeyword) !== false) {
			return false;
		}
	}
	return true;
}
function NameFilter_Auto(array $value): bool {
	if (!isset($value['name'])) {
		return false;
	}
	foreach (SubscribeIgnoreKeyword_Auto as $subscribeIgnoreKeywordAuto) {
		if (stripos($value['name'], $subscribeIgnoreKeywordAuto) !== false) {
			return false;
		}
	}
	return true;
}
function TypeFilter(array $value): bool {
	global $useReqFlag;
	if ($useReqFlag === 'clash') {
		if (isset($value['type']) && stripos($value['type'], 'vless') !== false) {
			return false;
		}
	}
	return true;
}
function FlowFilter(array $value): bool {
	global $reqFlag;
	if ($reqFlag === 'stash') {
		if (isset($value['flow']) && stripos($value['flow'], 'xtls-rprx-vision') !== false) {
			return false;
		}
	}
	return true;
}
function AddProxyNameToArr(array &$value) {
	global $reqFlag, $proxiesName, $proxiesNameAuto, $proxiesNameLowLatency, $proxiesNameCN;
	if ($reqFlag === 'stash') {
		$issetPassword = (isset($value['password']));
		$issetAuth = (isset($value['auth']));
		if ($issetPassword && !$issetAuth) {
			$value['auth'] = $value['password'];
		} else if ($issetAuth && !$issetPassword) {
			$value['password'] = $value['auth'];
		}
	}
	$proxiesName[] = "'" . trim($value['name'], "'") . "'";
	if (!isset($value['timeout'], $value['url'])) {
		$value['timeout'] = 5;
		$value['url'] = "'http://www.gstatic.com/generate_204'";
	}
	if (!isset($value['benchmark-timeout'], $value['benchmark-url'])) {
		$value['benchmark-timeout'] = 5;
		$value['benchmark-url'] = "'http://www.gstatic.com/generate_204'";
	}
	foreach (SubscribeBaseRuleProxiesNameMatchList_LowLatency as $lowLatencyMatch) {
		if (stripos($value['name'], $lowLatencyMatch) !== false) {
			$proxiesNameLowLatency[] = $value['name'];
			if (SubscribeAutoUseLowLatencyOnly && NameFilter_Auto($value)) {
				$proxiesNameAuto[] = $value['name'];
			}
			break;
		}
	}
	// 在 LowLatency 节点列表的不视为 CN 节点.
	if (!in_array($value['name'], $proxiesNameLowLatency) && (stripos($value['name'], '🇨🇳') !== false || stripos($value['name'], 'CN') !== false || stripos($value['name'], '中国') !== false)) {
		$proxiesNameCN[] = $value['name'];
		if ($reqFlag === 'stash') {
			$value['url'] = "'http://baidu.com'";
			$value['benchmark-url'] = "'http://baidu.com'";
		}
	} else if (!SubscribeAutoUseLowLatencyOnly && NameFilter_Auto($value)) {
		$proxiesNameAuto[] = $value['name'];
	}
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
if (SubscribeCache !== null) {
	if (!is_dir('SubscribeCache') && !mkdir('SubscribeCache')) {
		// 没有权限创建缓存文件夹.
		http_response_code(403);
		die("Bad permission.\n");
	}
	$lockRes = fopen('SubscribeCache/clash.lock', 'w');
	flock($lockRes, LOCK_EX);
}
$useReqFlag = ((isset(RewriteFlag[$reqFlag])) ? RewriteFlag[$reqFlag] : $reqFlag);
$subscribeBaseRule = @file_get_contents(SubscribeBaseRule);
$proxiesCount = 0;
$proxies = array();
$proxiesName = array();
$proxiesNameAuto = array();
$proxiesNameLowLatency = array();
$proxiesNameCN = array();
$subscribeURLCount = (count(SubscribeURL));
header('Content-Disposition: attachment; filename=Subscribe');
header('profile-update-interval: ' . SubscribeUpdateInterval);
foreach (SubscribeURL as $subscribeURL => $subscribeFlagParam) {
	$ruleSpaceIndent = 0;
	$detectProxies = -2; // -2: 等待检测代理标志, -1: 正在检测代理标志, 0: 已检测并提取代理.
	if ($subscribeFlagParam !== null && stripos($subscribeURL, str_replace(array('{useReqFlag}', '&', '?'), '', $subscribeFlagParam)) === false) {
		$subscribeURL .= str_replace('{useReqFlag}', $useReqFlag, $subscribeFlagParam);
	}
	$canCache = false;
	$useCache = false;
	if (SubscribeCache !== null) {
		if (stripos($subscribeURL, 'http') !== false) {
			$canCache = true;
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
	}
	$subscribeURLContent = @file($subscribeURL);
	if (empty($subscribeURLContent)) {
		continue;
	}
	if (!$useCache) {
		$subscribeUserInfo = GetHTTPHeader('subscription-userinfo');
		if (SubscribeUserInfoReturn && !empty($subscribeUserInfo) && (SubscribeUserInfoReturnAll || $subscribeURLCount === 1)) {
			header($subscribeUserInfo, false);
		}
		if ($canCache) {
			if (!empty($subscribeUserInfo)) {
				array_unshift($subscribeURLContent, "{$subscribeUserInfo}\n");
			}
			file_put_contents($subscribeCacheFilename, $subscribeURLContent);
		}
	}
	if (!in_array($reqFlag, SupportFlag)) {
		// 不支持的 flag, 若订阅设置为 Allow Flag, 则直接转发任一原始响应.
		if ($subscribeFlagParam !== null) {
			die(implode($subscribeURLContent));
		}
		continue;
	}
	$objMode = false;
	$lastProxiesKey = null;
	foreach ($subscribeURLContent as $subscribeLine) {
		if (empty($subscribeLine)) {
			continue;
		}
		if (SubscribeUserInfoReturn && $useCache && (SubscribeUserInfoReturnAll || $subscribeURLCount === 1) && ($subscribeUserInfo = stristr($subscribeLine, 'subscription-userinfo')) !== false) {
			header($subscribeUserInfo, false);
			continue;
		}
		if ($detectProxies === -2 && trim($subscribeLine) === 'proxies:') {
			// 抓住代理节点标志!
			$detectProxies = -1;
		} else if ($detectProxies === -1) {
			if ($ruleSpaceIndent === 0 && (($spaceIndent = strspn($subscribeLine, ' ', 0, 8)) > 0)) {
				$ruleSpaceIndent = $spaceIndent;
				$ruleSpaceIndentStr = str_repeat(' ', $ruleSpaceIndent);
				$ruleSpace3IndentStr = str_repeat(' ', ($ruleSpaceIndent * 3));
			}
			if (!isset($ruleSpaceIndentStr) || stripos($subscribeLine, $ruleSpaceIndentStr) === 0) {
				$trimSubscribeLine = trim($subscribeLine);
				if (stripos($trimSubscribeLine, '-') === 0) {
					$proxiesCount++;
					if (stripos($trimSubscribeLine, '{') !== false && stripos($trimSubscribeLine, '}') !== false) {
						$objMode = true;
					}
				}
				if ($objMode) {
					$subscribeLineObjArr = explode(',', substr(substr(trim($trimSubscribeLine, '- '), 1), 0, -1));
					$tmpObjValue = '';
					$lCount = 0;
					foreach ($subscribeLineObjArr as $objValue) {
						if (($tmpLCount = substr_count($objValue, '{') - substr_count($objValue, '}')) !== 0) {
							$lCount += $tmpLCount;
							$tmpObjValue .= ", {$objValue}";
							if ($lCount !== 0) {
								continue;
							}
							$objValue = $tmpObjValue;
							$tmpObjValue = '';
						}
						$objValue = trim($objValue, ', ');
						$subscribeLineKVArr = explode(':', $objValue, 2);
						if (count($subscribeLineKVArr) === 2) {
							$key = trim($subscribeLineKVArr[0], ', ');
							$value = trim($subscribeLineKVArr[1], ', ');
							$proxies[$proxiesCount][$key] = $value;
						}
					}
				} else {
					if ($lastProxiesKey !== null && isset($ruleSpace3IndentStr) && stripos($subscribeLine, $ruleSpace3IndentStr) === 0) {
						$subscribeLineKV2Arr = explode(':', $trimSubscribeLine, 2);
						if (count($subscribeLineKV2Arr) === 2) {
							$proxies[$proxiesCount][$lastProxiesKey][$subscribeLineKV2Arr[0]] = trim($subscribeLineKV2Arr[1], ', ');
						}
						continue;
					}
					$subscribeLineKVArr = explode(':', $trimSubscribeLine, 2);
					if (count($subscribeLineKVArr) === 2) {
						$lastProxiesKey = trim($subscribeLineKVArr[0], ',{} ');
						if (!empty($subscribeLineKVArr[1])) {
							$proxies[$proxiesCount][$lastProxiesKey] = trim($subscribeLineKVArr[1], ', ');
						}
					}
				}
			} else if (stripos($subscribeLine, ':') !== false) {
				$detectProxies = 0;
			}
		}
	}
}
if (SubscribeCache !== null) {
	flock($lockRes, LOCK_UN);
	fclose($lockRes);
	unlink('SubscribeCache/clash.lock');
}
array_walk($proxies, function (&$value, $key) {
	if (!NameFilter($value) || !TypeFilter($value) || !FlowFilter($value)) {
		$value = null;
		return;
	}
	AddProxyNameToArr($value);
});
$proxiesName = array_diff($proxiesName, $proxiesNameCN); // 取差集, 去除 CN 节点.
$proxiesStr = '';
foreach ($proxies as $proxy) {
	if ($proxy === null) {
		continue;
	}
	$tmpProxiesStr = '';
	foreach ($proxy as $key => $value) {
		$tmpProxiesStr .= "{$key}: ";
		if (is_array($value)) {
			$tmpProxiesStr2 = "{ ";
			foreach ($value as $key2 => $value2) {
				$tmpProxiesStr2 .= "{$key2}: {$value2}, ";
			}
			$tmpProxiesStr .= trim($tmpProxiesStr2, ', ') . " }";
		} else {
			$tmpProxiesStr .= "{$value}";
		}
		$tmpProxiesStr .= ", ";
	}
	if (!empty($tmpProxiesStr))  {
		$proxiesStr .= SubscribeBaseRuleSpace1IndentStr . '- { '. trim($tmpProxiesStr, ', ') . " }\n";
	}
}
$proxiesStr = trim($proxiesStr);
if (empty($proxiesStr)) {
	die();
}
$proxiesNameStr = implode(', ', $proxiesName);
$proxiesNameAutoStr = ((count($proxiesNameAuto) > 0) ? implode(', ', $proxiesNameAuto) : 'DIRECT');
$proxiesNameLowLatencyStr = implode(', ', $proxiesNameLowLatency);
$proxiesNameCNStr = implode(', ', $proxiesNameCN);
if (empty($proxiesNameStr)) {
	$subscribeBaseRule = preg_replace('/, ?' . SubscribeBaseRuleProxiesNameTag . '/', '', $subscribeBaseRule);
}
if (empty($proxiesNameLowLatencyStr)) {
	$subscribeBaseRule = preg_replace('/, ?' . SubscribeBaseRuleProxiesNameTag_LowLatency . '/', '', $subscribeBaseRule);
}
if (empty($proxiesNameCNStr)) {
	$subscribeBaseRule = preg_replace('/, ?' . SubscribeBaseRuleProxiesNameTag_CN . '/', '', $subscribeBaseRule);
}
echo str_replace(array(SubscribeBaseRuleProxiesTag, SubscribeBaseRuleProxiesNameTag, SubscribeBaseRuleProxiesNameTag_Auto, SubscribeBaseRuleProxiesNameTag_LowLatency, SubscribeBaseRuleProxiesNameTag_CN), array($proxiesStr, $proxiesNameStr, $proxiesNameAutoStr, $proxiesNameLowLatencyStr, $proxiesNameCNStr), $subscribeBaseRule);
?>
