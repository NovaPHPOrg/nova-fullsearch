<?php

namespace nova\plugin\fullsearch;

class QueryChinese
{
	private static ?PhpAnalysis $instance = null;

	private static function instance(): PhpAnalysis
	{
		if (self::$instance === null) {
			PhpAnalysis::$loadInit = true;
			$pa = new PhpAnalysis('utf-8', 'utf-8', true);
			$pa->differMax = true;
			$pa->differFreq = true;
			$pa->unitWord = true;
			$pa->LoadDict();
			self::$instance = $pa;
		}
		return self::$instance;
	}

	static function get($str)
	{
		$pa = self::instance();
		$pa->SetSource($str);
		$pa->StartAnalysis();
		return $pa->GetFinallyResult();
	}
}

