<?php
set_time_limit(30);
ini_set('user_agent', 'lSubConfig/1.0 (Compatible with Clash.Meta)');
define('HTTPContext', stream_context_create(array('http' => array('timeout' => 6, 'method' => 'GET', 'protocol_version' => 1.1, 'header' => 'Connection: close'))));
define('SubscribeKey', array('DefaultSubscribeKey' => null, 'DefaultSubscribeKey-NoSubscribeURL' => array('NoSubscribeURL' => true)));
define('SubscribeBaseRuleFilename', 'clash-{ruleMode}_baserule.yml');
define('SubscribeBaseRuleSpace1IndentStr', '    ');
define('SubscribeBaseRuleProxiesTag', '----lPROXIES----');
define('SubscribeBaseRuleProxiesNameTag', '----lPROXIESNAME----');
define('SubscribeBaseRuleProxiesNameTag_Auto', '----lPROXIESNAME_AUTO----'); // 默认使用符合 Keyword 要求的 LowLatency 节点作为自动节点.
define('SubscribeBaseRuleProxiesNameTag_LowLatency', '----lPROXIESNAME_LOWLATENCY----'); // 匹配以下关键词的被视作 LowLatency 节点.
define('SubscribeBaseRuleProxiesNameMatchList_LowLatency', array('🇭🇰', 'HK', '香港', '🇹🇼', 'TW', '台湾', '🇯🇵', 'JP', '日本'));
define('SubscribeBaseRuleProxiesNameTag_CN', '----lPROXIESNAME_CN----');
define('SubscribeCache', 3600); // Seconds or null.
define('SubscribeUpdateInterval', 12); // Hours or null.
define('SubscribeIgnoreError', false); // 即使所有订阅发生错误也返回配置文件. 适用于自带节点的 BaseRule.
define('SubscribeAutoUseLowLatencyOnly', true); // 默认仅使用 LowLatency 节点作为自动节点.
define('SubscribeIgnoreKeyword_Auto', array('IPv6')); // 不使用仅 IPv6 节点作为自动节点.
define('SubscribeIgnoreKeyword_AutoExtend', array('IEPL')); // 适用于非 HQ (High Quality) 的情况.
define('SubscribeIgnoreKeyword', array('套餐', '到期', '流量', '重置', '官网', '最新', '以上', '以下', '永久', '软件', '测试', 'Test'));
define('SubscribeUserInfoReturn', true);
define('SubscribeUserInfoReturnAll', false);
define('DefaultFlag', 'clash');
define('SupportFlag', array('clash', 'meta', 'stash', 'stash2'));
define('RewriteFlag', array('stash' => 'meta', 'stash2' => 'meta')); // Support first before rewriting.
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
function NameFilter_Auto(array $value, bool $highQuality): bool {
	if (!isset($value['name'])) {
		return false;
	}
	foreach (SubscribeIgnoreKeyword_Auto as $subscribeIgnoreKeywordAuto) {
		if (stripos($value['name'], $subscribeIgnoreKeywordAuto) !== false) {
			return false;
		}
	}
	if (!$highQuality) {
		foreach (SubscribeIgnoreKeyword_AutoExtend as $subscribeIgnoreKeywordAutoExtend) {
			if (stripos($value['name'], $subscribeIgnoreKeywordAutoExtend) !== false) {
				return false;
			}
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
	global $reqFlag, $hqMode, $proxiesName, $proxiesNameAuto, $proxiesNameLowLatency, $proxiesNameCN;
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
		$value['timeout'] = 5000;
		$value['url'] = "'http://www.gstatic.com/generate_204'";
	}
	if (!isset($value['benchmark-timeout'], $value['benchmark-url'])) {
		$value['benchmark-timeout'] = 5;
		$value['benchmark-url'] = "'http://www.gstatic.com/generate_204'";
	}
	foreach (SubscribeBaseRuleProxiesNameMatchList_LowLatency as $lowLatencyMatch) {
		if (stripos($value['name'], $lowLatencyMatch) !== false) {
			$proxiesNameLowLatency[] = $value['name'];
			if (SubscribeAutoUseLowLatencyOnly && NameFilter_Auto($value, $hqMode)) {
				$proxiesNameAuto[] = $value['name'];
			}
			break;
		}
	}
	// 在 LowLatency 节点列表的不视为 CN 节点.
	if (!in_array($value['name'], $proxiesNameLowLatency) && (stripos($value['name'], '🇨🇳') !== false || stripos($value['name'], 'CN') !== false || stripos($value['name'], '中国') !== false)) {
		$proxiesNameCN[] = $value['name'];
		if ($reqFlag === 'stash' || $reqFlag === 'stash2') {
			$value['url'] = "'http://baidu.com'";
			$value['benchmark-url'] = "'http://baidu.com'";
		}
	} else if (!SubscribeAutoUseLowLatencyOnly && NameFilter_Auto($value, $hqMode)) {
		$proxiesNameAuto[] = $value['name'];
	}
}
header('Content-Type: text/plain; charset=UTF-8');
$key = ((isset($_GET['k']) && array_key_exists(trim($_GET['k']), SubscribeKey)) ? trim($_GET['k']) : null);
if (PHP_SAPI !== 'cli') {
	if ($key === null) {
		http_response_code(404);
		die("File not found.\n");
	}
}
$keyPolicy = (($key !== null) ? SubscribeKey[$key] : null);
$noSubscribeURLMode = ($keyPolicy !== null && isset($keyPolicy['NoSubscribeURL']) && $keyPolicy['NoSubscribeURL'] === true);
$reqFlag = ((!empty($_GET['flag'])) ? trim(strtolower($_GET['flag'])) : DefaultFlag);
$reqFeats = array();
if (!empty($_GET['feat'])) {
	$reqFeats = explode(',', $_GET['feat']);
	foreach ($reqFeats as $key => &$reqFeat) {
		if (!ctype_alnum(str_replace('!', '', $reqFeat))) {
			unset($reqFeats[$key]);
			continue;
		}
		$reqFeat = trim(strtolower($reqFeat));
	}
	usort($reqFeats, function ($a, $b) {
		return (strlen($b) - strlen($a));
	});
}
if (count($reqFeats) <= 0) { // 包括 feat:NoSubscribeURL.
	$reqFeats[] = 'Default';
}
if (!in_array($reqFlag, RecognizeFlag)) {
	// 不认识的 flag, 直接拒绝响应.
	http_response_code(403);
	die("Bad flag.\n");
}
if (SubscribeCache !== null && !$noSubscribeURLMode) {
	if (!is_dir('SubscribeCache') && !mkdir('SubscribeCache')) {
		// 没有权限创建缓存文件夹.
		http_response_code(403);
		die("Bad permission.\n");
	}
	$lockRes = fopen('SubscribeCache/clash.lock', 'w');
	$waitSec = 0;
	while (!flock($lockRes, LOCK_EX | LOCK_NB)) {
		if (($waitSec++) > 12) {
			flock($lockRes, LOCK_UN);
		}
		sleep(1);
	}
}
$allowLAN = ((isset($_GET['allow_lan']) && $_GET['allow_lan'] === 'true') ? 'true' : 'false');
$bindAddress = ((!empty($_GET['bind_address'])) ? trim(strtolower($_GET['bind_address'])) : '127.0.0.1');
$enableTUN = ((isset($_GET['enable_tun']) && $_GET['enable_tun'] === 'true') ? 'true' : 'false');
$useReqFlag = ((isset(RewriteFlag[$reqFlag])) ? RewriteFlag[$reqFlag] : $reqFlag);
$hqMode = ((isset($_GET['hq']) && $_GET['hq'] === 'true') ? true : false);
if (!empty($_GET['mode'])) {
	$subscribeBaseRuleMode = trim(strtolower($_GET['mode']));
	$subscribeBaseRuleFilename = str_replace('-{ruleMode}', "-{$subscribeBaseRuleMode}", SubscribeBaseRuleFilename);
} else {
	$subscribeBaseRuleMode = 'normal';
	$subscribeBaseRuleFilename = str_replace('-{ruleMode}', '', SubscribeBaseRuleFilename);
}
if ($subscribeBaseRuleMode === 'relay' && $noSubscribeURLMode) {
	http_response_code(404);
	die("Bad permission.\n");
}
if (!is_file($subscribeBaseRuleFilename)) {
	http_response_code(404);
	die("Bad subscribe baserule filename.\n");
}
$subscribeBaseRule = @file_get_contents($subscribeBaseRuleFilename);
$proxiesCount = 0;
$proxies = array();
$proxiesName = array();
$proxiesNameAuto = array();
$proxiesNameLowLatency = array();
$proxiesNameCN = array();
$subscribeURLCount = (count(SubscribeURL));
$subscribeBaseRuleMode[0] = strtoupper($subscribeBaseRuleMode[0]);
header('Content-Disposition: attachment; filename="Subscribe (' .  $subscribeBaseRuleMode . ')"');
header('profile-update-interval: ' . SubscribeUpdateInterval);
if (!$noSubscribeURLMode) {
	foreach (SubscribeURL as $subscribeURL => $subscribeFlagParam) {
		unset($ruleSpaceIndentStr, $ruleSpaceIndentStrWithoutMinus, $ruleSpace3IndentStr);
		$ruleSpaceIndent = 0;
		$detectProxies = -2; // -2: 等待检测代理标志, -1: 正在检测代理标志, 0: 已检测并提取代理.
		if ($subscribeFlagParam !== null) {
			preg_match_all('/{rep:(.*?)\|(.*?)}/', $subscribeFlagParam, $repMatches);
			if (count($repMatches) > 2) {
				foreach ($repMatches[1] as $repKey => $repStr) {
					if ($useReqFlag === trim($repStr)) {
						$useReqFlag = trim($repMatches[2][$repKey]);
						break;
					}
				}
			}
			if (stripos($subscribeURL, str_replace(array('{useReqFlag}', '&', '?'), '', $subscribeFlagParam)) === false) {
				$subscribeURL .= str_replace('{useReqFlag}', $useReqFlag, $subscribeFlagParam);
			}
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
		$subscribeURLContent = @file($subscribeURL, 0, HTTPContext);
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
			$trimSubscribeLine = trim($subscribeLine);
			if ($detectProxies === -2 && $trimSubscribeLine === 'proxies:') {
				// 抓住代理节点标志!
				$detectProxies = -1;
			} else if ($detectProxies === -1) {
				if ($ruleSpaceIndent === 0 && (($spaceIndent = strspn($subscribeLine, ' ', 0, 8)) > 0)) {
					$ruleSpaceIndent = $spaceIndent;
					$ruleSpaceIndentStr = str_repeat(' ', $ruleSpaceIndent);
					$ruleSpaceIndentStrWithoutMinus = '-' . str_repeat(' ', ($ruleSpaceIndent - 1));
					$ruleSpace3IndentStr = str_repeat(' ', ($ruleSpaceIndent * 3));
				}
				if (!isset($ruleSpaceIndentStr) || stripos($subscribeLine, ($subscribeLine[0] === '-' ? $ruleSpaceIndentStrWithoutMinus : $ruleSpaceIndentStr)) === 0) {
					if ($trimSubscribeLine[0] === '-') {
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
							$lastProxiesKey = trim($subscribeLineKVArr[0], ',{} -');
							if (!empty($subscribeLineKVArr[1])) {
								$proxies[$proxiesCount][$lastProxiesKey] = trim($subscribeLineKVArr[1], ', ');
							}
						}
					}
				} else if ($trimSubscribeLine[0] !== '-' && stripos($trimSubscribeLine, ':') !== false) {
					$detectProxies = 0;
					break;
				}
			}
		}
	}
	if (SubscribeCache !== null) {
		flock($lockRes, LOCK_UN);
		fclose($lockRes);
		@unlink('SubscribeCache/clash.lock');
	}
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
if (!SubscribeIgnoreError && $subscribeURLCount > 0 && !$noSubscribeURLMode && empty($proxiesStr)) {
	die();
}
$proxiesNameStr = implode(', ', $proxiesName);
$proxiesNameAutoStr = ((count($proxiesNameAuto) > 0) ? implode(', ', $proxiesNameAuto) : 'DIRECT');
$proxiesNameLowLatencyStr = implode(', ', $proxiesNameLowLatency);
$proxiesNameCNStr = implode(', ', $proxiesNameCN);
foreach (SupportFlag as $supportFlag) {
	if ($reqFlag === $supportFlag) {
		continue;
	}
	$subscribeBaseRule = preg_replace('/.*# *?' . $supportFlag . ' *?$(\r)?(\n)/im', '', $subscribeBaseRule);
}
if ($noSubscribeURLMode) {
	$reqFeats[] = strtolower('NoSubscribeURL');
	//$subscribeBaseRule = preg_replace('/.*# *?feat: ?!(NoSubscribeURL).*$(\r)?(\n)/im', '', $subscribeBaseRule);
	//$subscribeBaseRule = preg_replace('/# *?feat: ?(NoSubscribeURL).*?( |$)/im', '', $subscribeBaseRule);
} else {
	$reqFeats[] = strtolower('!NoSubscribeURL');
	//$subscribeBaseRule = preg_replace('/.*# *?feat: ?(NoSubscribeURL).*$(\r)?(\n)/im', '', $subscribeBaseRule);
	//$subscribeBaseRule = preg_replace('/# *?feat: ?!(NoSubscribeURL).*?( |$)/im', '', $subscribeBaseRule);
}
$subscribeBaseRule = preg_replace_callback(
	'/.*# *?feat: ?(.*)$(\r)?(\n)/im',
	function ($matches) use ($reqFeats) {
		if (count($matches) >= 2) {
			$rawFeats = array_map('trim', explode(',', strtolower($matches[1])));
            $linePositiveFeats = [];
            $lineNegativeFeats = [];
            foreach ($rawFeats as $feat) {
                if (substr($feat, 0, 1) !== '!') {
                    $linePositiveFeats[] = $feat;
                } else {
                    $lineNegativeFeats[] = substr($feat, 1);
                }
            }
        	if (count($lineNegativeFeats) > 0 && array_intersect($reqFeats, $lineNegativeFeats)) {
            	return '';
            }
            if (count($linePositiveFeats) > 0 && !array_intersect($reqFeats, $linePositiveFeats)) {
            	return '';
            }
		}
		return $matches[0];
	},
	$subscribeBaseRule
);
$subscribeBaseRule = preg_replace('/ *$/im', '', $subscribeBaseRule);
if (empty($proxiesNameStr)) {
	$subscribeBaseRule = preg_replace('/, ?' . SubscribeBaseRuleProxiesNameTag . '/m', '', $subscribeBaseRule);
}
if (empty($proxiesNameLowLatencyStr)) {
	$subscribeBaseRule = preg_replace('/, ?' . SubscribeBaseRuleProxiesNameTag_LowLatency . '/m', '', $subscribeBaseRule);
}
if (empty($proxiesNameCNStr)) {
	$subscribeBaseRule = preg_replace('/, ?' . SubscribeBaseRuleProxiesNameTag_CN . '/m', '', $subscribeBaseRule);
}
$subscribeFinalRule = str_replace(array(SubscribeBaseRuleProxiesTag, SubscribeBaseRuleProxiesNameTag, SubscribeBaseRuleProxiesNameTag_Auto, SubscribeBaseRuleProxiesNameTag_LowLatency, SubscribeBaseRuleProxiesNameTag_CN, '----lAllowLAN----', '----lBindAddress----', '----lEnableTUN----'), array($proxiesStr, $proxiesNameStr, $proxiesNameAutoStr, $proxiesNameLowLatencyStr, $proxiesNameCNStr, $allowLAN, "'{$bindAddress}'", $enableTUN), $subscribeBaseRule);
$targetHost = (isset($_GET['host']) ? trim(strtolower($_GET['host'])) : 'p11.douyinpic.com');
//$subscribeFinalRule = str_replace('tms.dingtalk.com', '----lPROXIESHOST----', $subscribeFinalRule);
$subscribeFinalRule = str_replace('----lPROXIESHOST----', $targetHost, $subscribeFinalRule);
echo $subscribeFinalRule;
?>
