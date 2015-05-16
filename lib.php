<?php

$config['proxy_name'] = 'wwwcache.gla.ac.uk';
$config['proxy_port'] = 8080;

$config['proxy_name'] = '';
$config['proxy_port'] = '';

//--------------------------------------------------------------------------------------------------
/**
 * @brief Test whether HTTP code is valid
 *
 * HTTP codes 200 and 302 are OK.
 *
 * For JSTOR we also accept 403
 *
 * @param HTTP code
 *
 * @result True if HTTP code is valid
 */
function HttpCodeValid($http_code)
{
	if ( ($http_code == '200') || ($http_code == '302') || ($http_code == '403'))
	{
		return true;
	}
	else{
		return false;
	}
}


//--------------------------------------------------------------------------------------------------
/**
 * @brief GET a resource
 *
 * Make the HTTP GET call to retrieve the record pointed to by the URL. 
 *
 * @param url URL of resource
 *
 * @result Contents of resource
 */
function get($url, $userAgent = '', $timeout = 0)
{
	global $config;
	
	$data = '';
	
	$ch = curl_init(); 
	curl_setopt ($ch, CURLOPT_URL, $url); 
	curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1); 
	curl_setopt ($ch, CURLOPT_FOLLOWLOCATION,	1); 
	//curl_setopt ($ch, CURLOPT_HEADER,		  1);  

	if (0)
	{
		curl_setopt ($ch, CURLOPT_COOKIEJAR, 'cookie.txt');
	}
	else
	{
		// Set cookie manually if we need to
		curl_setopt($ch, CURLOPT_COOKIE, 'GSP=ID=45d0cce2a9907b8b:NT=1370262658:S=vsd_aZY8BDJLXBd4; SID=DQAAAOEAAABCtYOQjDW6sHEMScrgxVS_jmafvfenvCU_xPeYpUJCTpKhe5-W79-DRb94ejN3Qx7Pc213g53nGN_YeiNRxChpZ2tAJzbTkggb0Mzx7aKrrO6lXtE8hZkUP2itkcqgC2CMMopkDoyeNH70Lepxy7dMZiQjOM0RorpYuNayi2-x4Yqo6HvATHJc7t2C97LpNCHw0J6GsC5ns88RZyqEJIF90eBxkKQmYEXMEdos750Aa_FmO2C6ydznJOPXocxL2RSAnq5CWK2llRBeFlCFGA3nRgqirO-xZCdheiw_XfUsdkHkdpy78SpQQpU07qpfvY4; APISID=8qS4v5b92z2mFT1V/A7GexiFHL-CMKt8N3; HSID=AA9HjA4Ps-B63hWet; NID=67=I1nCCJhVUhIg5DNQlS1AxOwdTwcGxX-fsmPs8LsM78z86EF1BIXykVXjPpHXj723fs1Yjpkp4XBCQc9gXvhd6Tt8SBYImHst_xfd7JnCcLF0dzvXYq00RuxnbGmCuC_enI6GyEDHIl6a7bsWvea52CCUGEj2TyP_QFLaFGMO9xu1_KZGLGJlKQQoryk; PREF=ID=45d0cce2a9907b8b:U=cc9b0852a7bf3f3c:FF=0:LD=en:TM=1369223666:LM=1369249075:S=tnrZy4gbq__3uv9f; SS=DQAAAN8AAAAMCpjHAJFkvVEINf1uUTRnOpGLo8ijZBE-3NSawmbhbZ2asQ0kCZYr5W15a7Nyi31hznHT-wOQPMHAGwDjI39I2y7FwfqU4EfL9XksGw7J6f3O7CsM0OoI7yy_7hEGq5fx64Erxf3c-1wogJyexmL4SDjsocZU7zTMSJcoYjoRbgLgVrfT8jp9XC0adS2sLFthHCiss7zIcjJs');
	}
	
	if ($userAgent != '')
	{
		curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
	}	
	
	if ($timeout != 0)
	{
		curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
	}
	
	if ($config['proxy_name'] != '')
	{
		curl_setopt ($ch, CURLOPT_PROXY, $config['proxy_name'] . ':' . $config['proxy_port']);
	}
	
			
	$curl_result = curl_exec ($ch); 
	
	//echo $curl_result;
	
	if (curl_errno ($ch) != 0 )
	{
		echo "CURL error: ", curl_errno ($ch), " ", curl_error($ch);
	}
	else
	{
		$info = curl_getinfo($ch);
		
		 //$header = substr($curl_result, 0, $info['header_size']);
		//echo $header;
		
		
		$http_code = $info['http_code'];
		
		//echo "<p><b>HTTP code=$http_code</b></p>";
		
		if (HttpCodeValid ($http_code))
		{
			$data = $curl_result;
		}
	}
	return $data;
}

?>
