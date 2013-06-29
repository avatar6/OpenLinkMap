<?php
	/*
	OpenLinkMap Copyright (C) 2010 Alexander Matheisen
	This program comes with ABSOLUTELY NO WARRANTY.
	This is free software, and you are welcome to redistribute it under certain conditions.
	See http://wiki.openstreetmap.org/wiki/OpenLinkMap for details.
	*/


	require_once("functions.php");
	// include translation file
	includeLocale($_GET['lang']);

	$format = $_GET['format'];
	$id = $_GET['id'];
	$type = $_GET['type'];
	// offset of user's timezone to UTC
	$offset = offset($_GET['offset']);
	$callback = $_GET['callback'];

	date_default_timezone_set('UTC');


	// protection of sql injections
	if (!isValidType($type) || !isValidId($id))
	{
		echo "NULL";
		exit;
	}

	// get the most important langs of the user
	$langs = getLangs();
	if ($_GET['lang'])
		$langs[0] = $_GET['lang'];

	if (!getDetails($db, $id, $type, $langs, $offset))
		echo "NULL";


	function getDetails($db, $id, $type, $langs, $offset)
	{
		global $format;
		global $callback;

		// request
		$request = "SELECT
				tags->'addr:street' AS \"street\",
				tags->'addr:housenumber' AS \"housenumber\",
				tags->'addr:country' AS \"country\",
				tags->'addr:postcode' AS \"postcode\",
				tags->'addr:city' AS \"city\",
				tags->'addr:housename' AS \"housename\",
				tags->'addr:suburb' AS \"suburb\",
				tags->'addr:province' AS \"province\",
				tags->'addr:unit' AS \"unit\",
				tags->'addr:floor' AS \"floor\",
				tags->'addr:door' AS \"door\",
				tags->'wikipedia' AS \"wikipedia\",
				tags->'phone' AS \"phone1\",
				tags->'contact:phone' AS \"phone2\",
				tags->'addr:phone' AS \"phone3\",
				tags->'phone:mobile' AS \"mobilephone1\",
				tags->'contact:mobile' AS \"mobilephone2\",
				tags->'fax' AS \"fax1\",
				tags->'contact:fax' AS \"fax2\",
				tags->'addr:fax' AS \"fax3\",
				tags->'website' AS \"website1\",
				tags->'url' AS \"website2\",
				tags->'url:official' AS \"website3\",
				tags->'contact:website' AS \"website4\",
				tags->'operator' AS \"operator\",
				tags->'email' AS \"email1\",
				tags->'contact:email' AS \"email2\",
				tags->'addr:email' AS \"email3\",
				tags->'opening_hours' AS \"openinghours\",
				tags->'service_times' AS \"servicetimes\",
				tags->'fee' AS \"fee\",
				tags->'toll' AS \"toll\",
				tags->'ref' AS \"ref\",
				tags->'capacity' AS \"capacity\",
				tags->'internet_access' AS \"internet_access\",
				tags->'image' AS \"image\",
				tags->'image:panorama' AS \"panorama\",
				tags->'description' AS \"description\",
				tags->'stars' AS \"stars\",
				tags->'cuisine' AS \"cuisine\",
				tags->'smoking' AS \"smoking\",
				tags->'biergarten' AS \"biergarten\",
				tags->'beer_garden' AS \"beer_garden\",
				tags->'brewery' AS \"beer\",
				tags->'microbrewery' AS \"microbrewery\",
				tags->'fuel:diesel' AS \"diesel\",
				tags->'fuel:GTL_diesel' AS \"gtldiesel\",
				tags->'fuel:HGV_diesel' AS \"hgvdiesel\",
				tags->'fuel:biodiesel' AS \"biodiesel\",
				tags->'fuel_octane_91' AS \"octane91\",
				tags->'fuel:octane_95' AS \"octane95\",
				tags->'fuel:octane_98' AS \"octane98\",
				tags->'fuel:octane_100' AS \"octane100\",
				tags->'fuel:octane_98_leaded' AS \"octane98l\",
				tags->'fuel:1_25' AS \"fuel25\",
				tags->'fuel:1_50' AS \"fuel50\",
				tags->'fuel:alcohol' AS \"alcohol\",
				tags->'fuel:ethanol' AS \"ethanol\",
				tags->'fuel:methanol' AS \"methanol\",
				tags->'fuel:svo' AS \"svo\",
				tags->'fuel:e85' AS \"e85\",
				tags->'fuel:biogas' AS \"biogas\",
				tags->'fuel:lpg' AS \"lpg\",
				tags->'fuel:cng' AS \"cng\",
				tags->'fuel:LH2' AS \"lh2\",
				tags->'fuel:electricity' AS \"electro\",
				tags->'fuel:adblue' AS \"adblue\",
				tags->'car_wash' AS \"carwash\",
				tags->'car_repair' AS \"carrepair\",
				tags->'shop' AS \"shop\",
				tags->'kiosk' AS \"kiosk\",
				tags->'ele' AS \"ele\",
				tags->'population' AS \"population\",
				tags->'iata' AS \"iata\",
				tags->'icao' AS \"icao\",
				tags->'disused' AS \"disused\",
				tags->'wheelchair' AS \"wheelchair\",
				tags->'wheelchair:toilets' AS \"wheelchair:toilets\",
				tags->'wheelchair:rooms' AS \"wheelchair:rooms\",
				tags->'wheelchair:access' AS \"wheelchair:access\",
				tags->'wheelchair:places' AS \"wheelchair:places\"
			FROM ".$type."s WHERE (id = ".$id.");";

		$wikipediarequest = "SELECT
								foo.keys, foo.values
							FROM (
								SELECT
									skeys(tags) AS keys,
									svals(tags) AS values
								FROM ".$type."s
								WHERE (id = ".$id.")
							) AS foo
							WHERE substring(foo.keys from 1 for 9) = 'wikipedia';";

		$namerequest = "SELECT
								foo.keys, foo.values
							FROM (
								SELECT
									skeys(tags) AS keys,
									svals(tags) AS values
								FROM ".$type."s
								WHERE (id = ".$id.")
							) AS foo
							WHERE substring(foo.keys from 1 for 4) = 'name';";

		// connnecting to database
		$connection = connectToDatabase($db);
		// if there is no connection
		if (!$connection)
			exit;

		$response = requestDetails($request, $connection);
		$wikipediaresponse = requestDetails($wikipediarequest, $connection);
		$nameresponse = requestDetails($namerequest, $connection);

		pg_close($connection);

		if ($response)
		{
			if ($format == "text")
				echo textMoredetailsOut($response[0], $nameresponse, $wikipediaresponse, $langs, $offset);
			else if ($format == "json")
				echo jsonMoredetailsOut($response[0], $nameresponse, $wikipediaresponse, $langs, $offset, $id, $type, $callback);
			else
				echo xmlMoredetailsOut($response[0], $nameresponse, $wikipediaresponse, $langs, $offset, $id, $type);

			return true;
		}
		else
			return false;
	}


	// text/html output of extdetails
	function textMoredetailsOut($response, $nameresponse, $wikipediaresponse, $langs, $offset = 0)
	{
		global $db, $id, $type;

		if ($response)
		{
			// setting header
			header("Content-Type: text/html; charset=UTF-8");
			$output = "<meta http-equiv=\"content-type\" content=\"text/html; charset=UTF-8\">";

			// translation of name
			if ($nameresponse)
				$name = getNameDetail($langs, $nameresponse);

			// if no name is set, use the poi type as name instead
			if ($name[0] == "")
			{
				$tags = getTags($db, $id, $type);
				foreach ($tags as $key => $value)
				{
					$tag = $key."=".$value;
					if (dgettext("tags", $tag) != "")
						$name[0] = dgettext("tags", $tag);
					if ($name[0] != $tag)
						break;
				}
			}

			$phone = getPhoneFaxDetail(array($response['phone1'], $response['phone2'], $response['phone3']));
			$mobilephone = getPhoneFaxDetail(array($response['mobilephone1'], $response['mobilephone2']));
			$fax = getPhoneFaxDetail(array($response['fax1'], $response['fax2'], $response['fax3']));
			$website = getWebsiteDetail(array($response['website1'], $response['website2'], $response['website3'], $response['website4']));
			$email = getMailDetail(array($response['email1'], $response['email2'], $response['email3']));

			// get wikipedia link and make translation
			if ($wikipediaresponse)
				$wikipedia = getWikipediaDetail($langs, $wikipediaresponse);

			$openinghours = getOpeninghoursDetail($response['openinghours']);
			$servicetimes = getOpeninghoursDetail($response['servicetimes']);

			// title and description
			if ($name || $response['description'])
			{
				$output .= "<div class=\"moreInfoBox\">\n";
					$output .= "<center>";
						$output .= "<dfn><b>".$name[0]."</b></dfn><br />\n";
						if ($response['description'])
							$output .= "<dfn>".$response['description']."</dfn><br />\n";
					$output .= "</center>\n";
				$output .= "</div>\n";
			}

			// address box
			if ($response['street'] || $response['housenumber'] || $response['country'] || $response['city'] || $response['postcode'] || $response['housename'])
			{
				$output .= "<div class=\"moreInfoBox\">\n";
				$output .= "<strong>"._("Address")."</strong>\n";
				$output .= "<table><tr><td>\n";
				// country-dependend format of address
				$output .= formatAddress($response, $response['country']);
				$output .= "</td></tr></table>\n";
				$output .= "</div>\n";
			}

			// contact box
			if ($phone || $fax || $email || $mobilephone || $website[0])
			{
				$output .= "<div class=\"moreInfoBox\">\n";
				$output .= "<strong>"._("Contact")."</strong>\n";
				$output .= "<table>\n";
					if ($phone)
					{
						$output .= "<tr><td><u>"._("Phone").":</u></td><td>";
						foreach ($phone as $phonenumber)
							$output .= " <a href=\"tel:".$phonenumber[0]."\">".$phonenumber[1]."</a>\n";
						$output .= "</td></tr>\n";
					}
					if ($mobilephone)
					{
						$output .= "<tr><td><u>"._("Mobile phone").":</u></td><td>";
						foreach ($mobilephone as $mobilephonenumber)
							$output .= " <a href=\"tel:".$mobilephonenumber[0]."\">".$mobilephonenumber[1]."</a>\n";
						$output .= "</td></tr>\n";
					}
					if ($fax)
					{
						$output .= "<tr><td><u>"._("Fax").":</u></td><td>";
						foreach ($fax as $faxnumber)
							$output .= " ".$faxnumber[1]."\n";
						$output .= "</td></tr>\n";
					}
					if ($email)
					{
						$output .= "<tr><td><u>"._("Email").":</u> </td><td>";
						foreach ($email as $emailaddress)
							$output .= " <a href=\"mailto:".$emailaddress."\">".$emailaddress."</a>\n";
						$output .= "</td></tr>\n";
					}
					if ($website)
					{
						$output .= "<tr><td><u>"._("Homepage").":</u></td><td>";
						foreach ($website as $webaddress)
						{
							if (($caption = strlen($webaddress[1]) > 31) && (strlen($webaddress[1]) > 34))
								$caption = substr($webaddress[1], 0, 31)."...";
							else
								$caption = $webaddress[1];
							$output .= "<a target=\"_blank\" href=\"".$webaddress[0]."\">".$caption."</a>\n";
						}
						$output .= "</td></tr>\n";
					}
				$output .= "</table>\n";
				$output .= "</div>\n";
			}

			// gastro box
			if ($response['cuisine'] || $response['stars'] || $response['smoking'] || $response['microbrewery'] || $response['beer'])
			{
				$output .= "<div class=\"moreInfoBox\">\n";
				$output .= "<strong>"._("Gastronomy")."</strong>\n";
				$output .= "<table>\n";
					// cuisine
					if ($response['cuisine'])
					{
						$cuisinetag = "cuisine=".str_replace(";", ", ", $response['cuisine']);
						$output .= "<tr><td><span><u>"._("Cuisine").":</u> </td><td>".(dgettext($lang."-tags", $cuisinetag) != "" ? dgettext($lang."-tags", $cuisinetag) : str_replace(";", ", ", $response['cuisine']))."</td></tr>\n";
					}
					// stars
					if ($response['stars'])
					{
						$output .= "<tr><td><span><u>"._("Stars").":</u> </td><td>";
						for ($response['stars']; $response['stars'] > 0; $response['stars']--)
							$output .= "<img class=\"star\" src=\"../img/star.png\"/>";
						$output .= "</td></tr>\n";
					}
					// smoking
					if ($response['smoking'])
					{
						$output .= "<tr><td><span><u>"._("Smoking").":</u> </td>";
						if ($response['smoking'] == "yes")
							$output .= "<td>"._("allowed")."</td></tr>\n";
						else if ($response['smoking'] == "no")
							$output .= "<td>"._("prohibited")."</td></tr>\n";
						else if ($response['smoking'] == "dedicated")
							$output .= "<td>"._("dedicated")."</td></tr>\n";
						else if ($response['smoking'] == "separated")
							$output .= "<td>"._("separated smoking area")."</td></tr>\n";
						else if ($response['smoking'] == "isolated")
							$output .= "<td>"._("Isolated area")."</td></tr>\n";
					}
					// beer sorts
					if ($response['beer'])
						$output .= "<tr><td><span><u>"._("Beer").":</u> </td><td>".str_replace(";", ", ", $response['beer'])."</td></tr>\n";
					// microbrewery
					if ($response['microbrewery'] == "yes")
						$output .= "<tr><td><span>"._("with microbrewery")."</td></tr>\n";
					// biergarten
					if (($response['biergarten'] == "yes") || ($response['beer_garden'] == "yes"))
						$output .= "<tr><td><span>"._("Beergarden")."</td></tr>\n";
				$output .= "</table>\n";
				$output .= "</div>\n";
			}

			// fuel box
			if ($response['carwash'] || $response['carrepair'] || $response['kiosk'] || ($response['diesel'] == "yes") || ($response['gtldiesel'] == "yes") || ($response['hgvdiesel'] == "yes") || ($response['biodiesel'] == "yes") || ($response['octane91'] == "yes") || ($response['octane95'] == "yes") || ($response['octane98'] == "yes") || ($response['octane100'] == "yes") || ($response['octane98l'] == "yes") || ($response['fuel25'] == "yes") || ($response['fuel50'] == "yes") || ($response['alcohol'] == "yes") || ($response['ethanol'] == "yes") || ($response['methanol'] == "yes") || ($response['svo'] == "yes") || ($response['e85'] == "yes") || ($response['biogas'] == "yes") || ($response['lpg'] == "yes") || ($response['cng'] == "yes") || ($response['lh2'] == "yes") || ($response['electro'] == "yes") || ($response['adblue'] == "yes"))
			{
				$output .= "<div class=\"moreInfoBox\">\n";
				$output .= "<strong>"._("Fuel")."</strong>\n";
				$output .= "<table>\n";
				// fuel sorts
				if ($response['diesel'] == "yes")
					$output .= "<tr><td><span>"._("Diesel")."</span></td></tr>\n";
				if ($response['gtldiesel'] == "yes")
					$output .= "<tr><td><span>"._("High quality synthetic Diesel")."</span></td></tr>\n";
				if ($response['hgvdiesel'] == "yes")
					$output .= "<tr><td><span>"._("HGV Diesel")."</span></td></tr>\n";
				if ($response['biodiesel'] == "yes")
					$output .= "<tr><td><span>"._("Biodiesel")."</span></td></tr>\n";
				if ($response['octane91'] == "yes")
					$output .= "<tr><td><span>"._("Octane 91")."</span></td></tr>\n";
				if ($response['octane95'] == "yes")
					$output .= "<tr><td><span>"._("Octane 95")."</span></td></tr>\n";
				if ($response['octane98'] == "yes")
					$output .= "<tr><td><span>"._("Octane 98")."</span></td></tr>\n";
				if ($response['octane100'] == "yes")
					$output .= "<tr><td><span>"._("Octane 100")."</span></td></tr>\n";
				if ($response['octane98l'] == "yes")
					$output .= "<tr><td><span>"._("Octane 98, leaded")."</span></td></tr>\n";
				if ($response['fuel25'] == "yes")
					$output .= "<tr><td><span>"._("Mixture 1:25")."</span></td></tr>\n";
				if ($response['fuel50'] == "yes")
					$output .= "<tr><td><span>"._("Mixture 1:50")."</span></td></tr>\n";
				if ($response['alcohol'] == "yes")
					$output .= "<tr><td><span>"._("Alcohol")."</span></td></tr>\n";
				if ($response['ethanol'] == "yes")
					$output .= "<tr><td><span>"._("Ethanol")."</span></td></tr>\n";
				if ($response['methanol'] == "yes")
					$output .= "<tr><td><span>"._("Methanol")."</span></td></tr>\n";
				if ($response['svo'] == "yes")
					$output .= "<tr><td><span>"._("Vegetable oil")."</span></td></tr>\n";
				if ($response['e10'] == "yes")
					$output .= "<tr><td><span>"._("Ethanol-Mixture E10 (10% Ethanol)")."</span></td></tr>\n";
				if ($response['e85'] == "yes")
					$output .= "<tr><td><span>"._("Ethanol-Mixture E85 (85% Ethanol)")."</span></td></tr>\n";
				if ($response['biogas'] == "yes")
					$output .= "<tr><td><span>"._("Biogas")."</span></td></tr>\n";
				if ($response['lpg'] == "yes")
					$output .= "<tr><td><span>"._("LPG")."</span></td></tr>\n";
				if ($response['cng'] == "yes")
					$output .= "<tr><td><span>"._("CNG")."</span></td></tr>\n";
				if ($response['lh2'] == "yes")
					$output .= "<tr><td><span>"._("Liquid hydrogen")."</span></td></tr>\n";
				if ($response['electro'] == "yes")
					$output .= "<tr><td><span>"._("Electricity")."</span></td></tr>\n";
				if ($response['adblue'] == "yes")
					$output .= "<tr><td><span>"._("AdBlue")."</span></td></tr>\n";
				$output .= "<br/>";
				// other properties of fuel station
				if ($response['carwash'] == "yes")
					$output .= "<tr><td><i>"._("Car wash")."</i></td></tr>\n";
				if ($response['carrepair'] == "yes")
					$output .= "<tr><td><i>"._("Car repair")."</i></td></tr>\n";
				if ($response['shop'] == "kiosk" || $response['kiosk'] == "yes")
					$output .= "<tr><td><i>"._("Shop")."</i></td></tr>\n";
				$output .= "</table>\n";
				$output .= "</div>\n";
			}

			// other box
			if ($response['operator'] || $response['capacity'] || $response['fee'] || $openinghours || $response['fee'] || $response['internet_access'] || $response['toll'] || $response['ref'])
			{
				$output .= "<div class=\"moreInfoBox\">\n";
				$output .= "<strong>"._("Other")."</strong>\n";
				$output .= "<table>\n";
					// opening hours
					if ($openinghours)
					{
						$output .= "<tr><td><span><u>"._("Opening hours").":</u><br />".$openinghours."</span></td></tr>\n";
						if (isOpen247($response['openinghours']))
							$output .= "<tr><td>&nbsp;&nbsp;<span class=\"open\">"._("Open 24/7")."</span></td></tr>\n";
						else if (isPoiOpen($response['openinghours'], $offset))
							$output .= "<tr><td>&nbsp;&nbsp;<span class=\"open\">"._("Now open")."</span></td></tr>\n";
						else if (isInHoliday($response['openinghours'], $offset))
							$output .= "<tr><td>&nbsp;&nbsp;<span class=\"maybeopen\">"._("Open on holiday")."</span></td></tr>\n";
						else
							$output .= "<tr><td>&nbsp;&nbsp;<span class=\"closed\">"._("Now closed")."</span></td></tr>\n";
					}
					// service times
					if ($servicetimes)
					{
						$output .= "<tr><td><span><u>"._("Service hours").":</u><br />".$servicetimes."</span></td></tr>\n";
						if (isPoiOpen($response['servicetimes'], $offset))
							$output .= "<tr><td>&nbsp;&nbsp;<span class=\"open\">"._("Now open")."</span></td></tr>\n";
						else if (isInHoliday($response['servicetimes'], $offset))
							$output .= "<tr><td>&nbsp;&nbsp;<span class=\"maybeopen\">"._("Open on holiday")."</span></td></tr>\n";
						else
							$output .= "<tr><td>&nbsp;&nbsp;<span class=\"closed\">"._("Now closed")."</span></td></tr>\n";
					}
					// operator
					if ($response['operator'])
						$output .= "<tr><td><span><u>"._("Operator").":</u> ".$response['operator']."</span></td></tr>\n";
					// capacity
					if ($response['capacity'])
						$output .= "<tr><td><span><u>"._("Capacity").":</u> ".$response['capacity']."</span></td></tr>\n";
					// ref
					if ($response['ref'])
						$output .= "<tr><td><span><u>"._("Reference")."</u>: ".$response['ref']."</span></td></tr>\n";
					// internet access
					if ($response['internet_access'])
					{
						if ($response['internet_access'] == "terminal")
							$output .= "<tr><td><span>"._("Internet terminal")."</span></td></tr>\n";
						else if ($response['internet_access'] == "wlan")
							$output .= "<tr><td><span>"._("WLAN hotspot")."</span></td></tr>\n";
						else if ($response['internet_access'] == "wired")
							$output .= "<tr><td><span>"._("Wired internet access")."</span></td></tr>\n";
						else if ($response['internet_access'] == "service")
							$output .= "<tr><td><span>"._("Internet access via service")."</span></td></tr>\n";
					}
					// fee
					if ($response['fee'] == "yes")
						$output .= "<tr><td><span>"._("With costs")."</span></td></tr>\n";
					else if ($response['fee'] == "no")
						$output .= "<tr><td><span>"._("For free")."</span></td></tr>\n";
					else if ($response['fee'] == "interval")
						$output .= "<tr><td><span>"._("Partly with costs")."</span></td></tr>\n";
					// toll
					if ($response['toll'] == "yes")
						$output .= "<tr><td><span>"._("Toll")."</span></td></tr>\n";
					// disused
					if ($response['disused'] == "yes")
						$output .= "<tr><td><span>"._("Disused")."</span></td></tr>\n";
				$output .= "</table>\n";
				$output .= "</div>\n";
			}

			// geographic box
			if ($response['ele'] || $response['population'] || $response['iata'] || $response['icao'])
			{
				$output .= "<div class=\"moreInfoBox\">\n";
				$output .= "<strong>"._("Geographic")."</strong>\n";
				$output .= "<table>\n";
					if ($response['ele'])
						$output .= "<tr><td><span><u>"._("Height about sea level").":</u> ".$response['ele']."m</span></td></tr>\n";
					if ($response['population'])
						$output .= "<tr><td><span><u>"._("Population").":</u> ".number_format($response['population'], 0, ',', '.')."</span></td></tr>\n";
					if ($response['iata'])
						$output .= "<tr><td><span><u>"._("IATA").":</u> ".$response['iata']."</span></td></tr>\n";
					if ($response['icao'])
						$output .= "<tr><td><span><u>"._("ICAO").":</u> ".$response['icao']."</span></td></tr>\n";
				$output .= "</table>\n";
				$output .= "</div>\n";
			}

			// wheelchair box
			if ($response['wheelchair'] || $response['wheelchair:toilets'] || $response['wheelchair:rooms'] || $response['wheelchair:access'] || $response['wheelchair:places'])
			{
				$output .= "<div class=\"moreInfoBox\">\n";
				$output .= "<strong>"._("Wheelchair")."</strong>\n";
				$output .= "<table>\n";
					if ($response['wheelchair'])
					{
						if ($response['wheelchair'] == "yes")
							$output .= "<tr><td><span>"._("Accessible for wheelchairs")."</span></td></tr>\n";
						else if ($response['wheelchair'] == "no")
							$output .= "<tr><td><span>"._("Not accessible for wheelchairs")."</span></td></tr>\n";
						else if ($response['wheelchair'] == "limited")
							$output .= "<tr><td><span>"._("Limited accessible for wheelchairs")."</span></td></tr>\n";
					}
					if ($response['wheelchair:toilets'])
					{
						if ($response['wheelchair:toilets'] == "yes")
							$output .= "<tr><td><span>"._("Toilets accessible")."</span></td></tr>\n";
						else if ($response['wheelchair:toilets'] == "no")
							$output .= "<tr><td><span>"._("Toilets not accessible")."</span></td></tr>\n";
						else if ($response['wheelchair:toilets'] == "limited")
							$output .= "<tr><td><span>"._("Toilets limited accessible")."</span></td></tr>\n";
					}
					if ($response['wheelchair:rooms'])
					{
						if ($response['wheelchair:rooms'] == "yes" || $response['wheelchair:rooms'] != "no" || $response['wheelchair:rooms'] != "limited")
							$output .= "<tr><td><span>"._("All rooms accessible")."</span></td></tr>\n";
						else if ($response['wheelchair:rooms'] == "no")
							$output .= "<tr><td><span>"._("Rooms not accessible")."</span></td></tr>\n";
						else if ($response['wheelchair:rooms'] == "limited")
							$output .= "<tr><td><span>"._("Most rooms accessible")."</span></td></tr>\n";
					}
					if ($response['wheelchair:access'])
					{
						if ($response['wheelchair:access'] == "yes" || $response['wheelchair:access'] != "no" || $response['wheelchair:access'] != "limited")
							$output .= "<tr><td><span>"._("Building accessible")."</span></td></tr>\n";
						else if ($response['wheelchair:access'] == "no")
							$output .= "<tr><td><span>"._("Building not accessible")."</span></td></tr>\n";
						else if ($response['wheelchair:access'] == "limited")
							$output .= "<tr><td><span>"._("Building limited accessible")."</span></td></tr>\n";
					}
					if ($response['wheelchair:places'])
					{
						if ($response['wheelchair:places'] == "yes" || $response['wheelchair:places'] != "no" || $response['wheelchair:places'] != "limited")
							$output .= "<tr><td><span>"._("Places for wheelchairs")."</span></td></tr>\n";
						else if ($response['wheelchair:places'] == "no")
							$output .= "<tr><td><span>"._("No places for wheelchairs")."</span></td></tr>\n";
						else if ($response['wheelchair:places'] == "limited")
							$output .= "<tr><td><span>"._("Limited places for wheelchairs")."</span></td></tr>\n";
					}
				$output .= "</table>\n";
				$output .= "</div>\n";
			}

			// wikipedia box
			if ($wikipedia)
			{
				$output .= "<div class=\"moreInfoBox\">\n";
				$output .= "<table>\n";
					$output .= "<tr><td><strong>"._("Wikipedia")."</strong>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<i><a target=\"_blank\" id=\"moreWikipediaFull\" href=\"".$wikipedia[1]."\">"._("Full article...")."</a></i></td></tr>\n";
					// request first lines
					$output .= "<tr><td>".getWikipediaBeginning($wikipedia[1])."</td></tr>\n";
				$output .= "</table>\n";
				$output .= "</div>\n";
			}

			// image box, only images from domains listed on a whitelist are displayed
			if (imageDomainAllowed($response['image']))
			{
				$url = getImageUrl($response['image']);
				$tmp = parse_url($url);
				if (substr_count($tmp['host'], ".") > 1)
					$domain = substr($tmp['host'], strpos($tmp['host'], ".")+1);
				else
					$domain = $tmp['host'];

				$output .= "<div class=\"moreInfoBox\">\n";
				$output .= "<table>\n";

				// image from wikimedia commons
				if ($domain == "wikimedia.org")
				{
					// creating url to Wikimedia Commons page of this image
					$attribution = explode("/", $url);
					if (substr($url, 34, 16) == "special:filepath")
						$attribution = $attribution[5];
					else
						$attribution = $attribution[7];

					if ($wikipedia)
						$search = urldecode($wikipedia[2]);
					else
						$search = $name[0];

					$output .= "<tr><td><strong>"._("Image")."</strong>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<i id=\"moreWikipediaFull\"><a target=\"_blank\" href=\"http://commons.wikimedia.org/w/index.php?title=Special%3ASearch&search=".$search."\">"._("More images")."</a></i></td></tr>\n";
					$output .= "<tr><td id=\"loadingImage\"><img id=\"moreImage\" title=\""._("Fullscreen")."\" src=\"".getWikipediaThumbnailUrl($url)."\" /></td></tr>\n";
					$output .= "<tr><td><a target=\"_blank\" href=\"http://commons.wikimedia.org/wiki/File:".$attribution."\">"._("attribution-wikimedia.org")."</a></td></tr>\n";
				}
				// image from OpenStreetMap Wiki
				else if ($domain == "openstreetmap.org")
				{
					// creating url to Wikimedia Commons page of this image
					$attribution = explode("/", $url);
					if (substr($url, 35, 16) == "special:filepath")
						$attribution = $attribution[5];
					else
						$attribution = $attribution[7];

					if ($wikipedia)
						$search = urldecode($wikipedia[2]);
					else
						$search = $name[0];

					$output .= "<tr><td><strong>"._("Image")."</strong>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<i id=\"moreWikipediaFull\"><a target=\"_blank\" href=\"http://commons.wikimedia.org/w/index.php?title=Special%3ASearch&search=".$search."\">"._("More images")."</a></i></td></tr>\n";
					$output .= "<tr><td id=\"loadingImage\"><img id=\"moreImage\" title=\""._("Fullscreen")."\" src=\"".getOsmWikiThumbnailUrl($url)."\" /></td></tr>\n";
					$output .= "<tr><td><a target=\"_blank\" href=\"http://commons.wikimedia.org/wiki/File:".$attribution."\">"._("attribution-openstreetmap.org")."</a></td></tr>\n";
				}
				// image from other source
				else
				{
					$output .= "<tr><td><strong>"._("Image")."</strong></td></tr>\n";
					$output .= "<tr><td id=\"loadingImage\"><img id=\"moreImage\" title=\""._("Fullscreen")."\" src=\"".$url."\" /></td></tr>\n";
					$output .= "<tr><td><a target=\"_blank\" href=\""._("attribution-url-".$domain)."\">"._("attribution-".$domain)."</a></td></tr>\n";
				}

				$output .= "</table>\n";
				$output .= "</div>\n";
			}
			else if (getWikipediaImage($wikipedia[1]))
			{
				$image = getWikipediaImage($wikipedia[1]);

				$output .= "<div class=\"moreInfoBox\">\n";
				$output .= "<table>\n";
				$output .= "<tr><td><strong>"._("Image")."</strong>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<i id=\"moreWikipediaFull\"><a target=\"_blank\" href=\"http://commons.wikimedia.org/w/index.php?title=Special%3ASearch&search=".urldecode($wikipedia[2])."\">"._("More images")."</a></i></td></tr>\n";
					$output .= "<tr><td id=\"loadingImage\"><img id=\"moreImage\" title=\""._("Fullscreen")."\" src=\"".getWikipediaThumbnailUrl($image)."\" /></a></td></tr>\n";
					$output .= "<tr><td><a target=\"_blank\" href=\"".$image."\">"._("License, author, original page...")."</a></td></tr>\n";
				$output .= "</table>\n";
				$output .= "</div>\n";
			}

			/*
			// panorama, only images from domains listed on a whitelist are supported
			if (imageDomainAllowed($response['panorama']))
			{
				$attribution = explode("/", $response['panorama']);
				$output .= "<div class=\"moreInfoBox\">\n";
				$output .= "<table>\n";
					$output .= "<tr><td><strong>"._("Panorama")."</strong></td></tr>\n";
					$output .= "<tr><td><img id=\"morePanorama\" title=\""._("Fullscreen")."\" src=\"".getWikipediaThumbnailUrl($response['panorama'])."\" /></a></td></tr>\n";
					$output .= "<tr><td><a target=\"_blank\" href=\"http://commons.wikimedia.org/wiki/File:".$attribution[7]."\">"._("License, author, original page...")."</a></td></tr>\n";
				$output .= "</table>\n";
				$output .= "</div>\n";
			}
			*/

			$output .= "</div>\n";

			return $output;
		}
		else
			return false;
	}


	// output of details data in xml format
	function xmlMoreDetailsOut($response, $nameresponse, $wikipediaresponse, $langs = "en", $offset = 0, $id, $type)
	{
		if ($response)
		{
			$output = xmlStart("moredetails id=\"".$id."\" type=\"".$type."\"");

			$name = getNameDetail($langs, $nameresponse);

			$phone = getPhoneFaxDetail(array($response['phone1'], $response['phone2'], $response['phone3']));
			$fax = getPhoneFaxDetail(array($response['fax1'], $response['fax2'], $response['fax3']));
			$mobilephone = getPhoneFaxDetail(array($response['mobilephone1'], $response['mobilephone2']));
			$website = getWebsiteDetail(array($response['website1'], $response['website2'], $response['website3'], $response['website4']));
			$email = getMailDetail(array($response['email1'], $response['email2'], $response['email3']));

			// get wikipedia link and make translation
			if ($wikipediaresponse)
				$wikipedia = getWikipediaDetail($langs, $wikipediaresponse);

			$openinghours = getOpeninghoursDetail($response['openinghours']);
			$servicetimes = getOpeninghoursDetail($response['servicetimes']);

			// printing popup details
			if ($name)
			{
				$output .= "<name";
				if ($name[0])
					$output .= " lang=\"".$name[1]."\"";
				$output .= ">".$name[0]."</name>\n";
			 }

			 if ($response['description'])
			 	$output .= "<description>".$response['description']."</description>";

			// address information
			if ($response['street'] || $response['housenumber'] || $response['country'] || $response['postcode'] || $response['city'])
			{
				$output .= "<address>\n";
				if ($response['street'])
					$output .= "<street>".$response['street']."</street>\n";
				if ($response['housenumber'])
					$output .= "<housenumber>".$response['housenumber']."</housenumber>\n";
				if ($response['country'])
					$output .= "<country>".strtoupper($response['country'])."</country>\n";
				if ($response['postcode'])
					$output .= "<postcode>".$response['postcode']."</postcode>\n";
				if ($response['city'])
					$output .= "<city>".$response['city']."</city>\n";
				if ($response['suburb'])
					$output .= "<suburb>".$response['suburb']."</suburb>\n";
				$output .= "</address>\n";
			}

			// contact information
			if ($phone || $fax || $mobilephone || $email)
			{
				$output .= "<contact>\n";
				if ($phone)
					foreach ($phone as $phonenumber)
						$output .= " <phone>".$phonenumber[1]."</phone>";
				if ($fax)
					foreach ($fax as $faxnumber)
						$output .= " <fax>".$faxnumber[1]."</fax>";
				if ($mobilephone)
					foreach ($mobilephone as $mobilephonenumber)
						$output .= " <mobilephone>".$mobilephonenumber[1]."</mobilephone>";
				if ($email)
					foreach ($email as $emailaddress)
						$output .= "<email>".$emailaddress."</email>\n";
				$output .= "</contact>\n";
			}

			// website and wikipedia links
			if ($website)
			{
				$output .= "<web>\n";
				foreach ($website as $webaddress)
					if ($webaddress[0])
						$output .= "<website>".$webaddress[0]."</website>\n";
				$output .= "</web>\n";
			}

			// operator
			if ($response['operator'])
				$output .= "<operator>".$response['operator']."</operator>\n";

			// opening hours
			if ($openinghours)
			{
				$output .= "<openinghours state=\"";

				if (isPoiOpen($response['openinghours'], $offset))
					$output .= "open";
				else if (isInHoliday($response['openinghours'], $offset))
					$output .= "maybeopen";
				else
					$output .= "closed";

				$output .= "\">".$response['openinghours']."</openinghours>\n";
			}

			// service times
			if ($servicetimes)
			{
				$output .= "<servicetimes state=\"";

				if (isPoiOpen($response['servicetimes'], $offset))
					$output .= "open";
				else if (isInHoliday($response['servicetimes'], $offset))
					$output .= "maybeopen";
				else
					$output .= "closed";

				$output .= "\">".$response['servicetimes']."</servicetimes>\n";
			}

			// fuel details
			if ($response['carwash'] || $response['carrepair'] || $response['kiosk'] || ($response['diesel'] == "yes") || ($response['gtldiesel'] == "yes") || ($response['hgvdiesel'] == "yes") || ($response['biodiesel'] == "yes") || ($response['octane91'] == "yes") || ($response['octane95'] == "yes") || ($response['octane98'] == "yes") || ($response['octane100'] == "yes") || ($response['octane98l'] == "yes") || ($response['fuel25'] == "yes") || ($response['fuel50'] == "yes") || ($response['alcohol'] == "yes") || ($response['ethanol'] == "yes") || ($response['methanol'] == "yes") || ($response['svo'] == "yes") || ($response['e85'] == "yes") || ($response['biogas'] == "yes") || ($response['lpg'] == "yes") || ($response['cng'] == "yes") || ($response['lh2'] == "yes") || ($response['electro'] == "yes") || ($response['adblue'] == "yes"))
			{
				$output .= "<fuel>\n";
					// fuel sorts
					if ($response['diesel'] == "yes")
						$output .= "<diesel />\n";
					if ($response['gtldiesel'] == "yes")
						$output .= "<gtldiesel />\n";
					if ($response['hgvdiesel'] == "yes")
						$output .= "<hgvdiesel />\n";
					if ($response['biodiesel'] == "yes")
						$output .= "<biodiesel />\n";
					if ($response['octane91'] == "yes")
						$output .= "<octane91 />\n";
					if ($response['octane95'] == "yes")
						$output .= "<octane95 />\n";
					if ($response['octane98'] == "yes")
						$output .= "<octane98 />\n";
					if ($response['octane100'] == "yes")
						$output .= "<octane100 />\n";
					if ($response['octane98l'] == "yes")
						$output .= "<octane98l />\n";
					if ($response['fuel25'] == "yes")
						$output .= "<1:25 />\n";
					if ($response['fuel50'] == "yes")
						$output .= "<1:50 />\n";
					if ($response['alcohol'] == "yes")
						$output .= "<alcohol />\n";
					if ($response['ethanol'] == "yes")
						$output .= "<ethanol />\n";
					if ($response['methanol'] == "yes")
						$output .= "<methanol />\n";
					if ($response['svo'] == "yes")
						$output .= "<svo />\n";
					if ($response['e10'] == "yes")
						$output .= "<e10 />\n";
					if ($response['e85'] == "yes")
						$output .= "<e85 />\n";
					if ($response['biogas'] == "yes")
						$output .= "<biogas />\n";
					if ($response['lpg'] == "yes")
						$output .= "<lpg />\n";
					if ($response['cng'] == "yes")
						$output .= "<cng />\n";
					if ($response['lh2'] == "yes")
						$output .= "<lh2 />\n";
					if ($response['electro'] == "yes")
						$output .= "<electricity />\n";
					if ($response['adblue'] == "yes")
						$output .= "<adblue />\n";
					// other properties of fuel station
					if ($response['carwash'] == "yes")
						$output .= "<carwash />\n";
					if ($response['carrepair'] == "yes")
						$output .= "<carrepair />\n";
					if ($response['shop'] == "kiosk" || $response['kiosk'] == "yes")
						$output .= "<kiosk />\n";
				$output .= "</fuel>\n";
			}

			// gastro
			if ($response['cuisine'] || $response['stars'] || $response['smoking'] || $response['microbrewery'] || $response['beer'])
			{
				$output .= "<gastronomy>\n";
					// cuisine
					if ($response['cuisine'])
						$output .= "<cuisine>".str_replace(";", ",", $response['cuisine'])."</cuisine>\n";
					// stars
					if ($response['stars'])
						$output .= "<stars>".$response['stars']."</stars>\n";
					// smoking
					if ($response['smoking'])
						$output .= "<smoking>".$response['smoking']."</smoking>";
					// beer sorts
					if ($response['beer'])
						$output .= "<beer>".str_replace(";", ",", $response['beer'])."</beer>\n";
					// microbrewery
					if ($response['microbrewery'] == "yes")
						$output .= "<microbrewery />\n";
					// biergarten
					if (($response['biergarten'] == "yes") || ($response['beer_garden'] == "yes"))
						$output .= "<biergarten />\n";
				$output .= "</gastronomy>\n";
			}

			// geographic
			if ($response['ele'] || $response['population'] || $response['iata'] || $response['icao'])
			{
				$output .= "<geographic>\n";
					if ($response['ele'])
						$output .= "<ele>".$response['ele']."m</ele>\n";
					if ($response['population'])
						$output .= "<population>".$response['population']."</population>\n";
					if ($response['iata'])
						$output .= "<iata>".$response['iata']."</iata>\n";
					if ($response['icao'])
						$output .= "<icao>".$response['icao']."</icao>\n";
				$output .= "</geographic>\n";
			}

			// wheelchair
			if ($response['wheelchair'] || $response['wheelchair:toilets'] || $response['wheelchair:rooms'] || $response['wheelchair:access'] || $response['wheelchair:places'])
			{
				$output .= "<accessibility>\n";

				if ($response['wheelchair'])
					$output .= "<wheelchair>".$response['wheelchair']."</wheelchair>\n";
				if ($response['wheelchair:toilets'])
					$output .= "<wheelchair:toilets>".$response['wheelchair:toilets']."</wheelchair:toilets>\n";
				if ($response['wheelchair:rooms'])
					$output .= "<wheelchair:rooms>".$response['wheelchair:rooms']."</wheelchair:rooms>\n";
				if ($response['wheelchair:access'])
					$output .= "<wheelchair:access>".$response['wheelchair:access']."</wheelchair:access>\n";
				if ($response['wheelchair:places'])
					$output .= "<wheelchair:places>".$response['wheelchair:places']."</wheelchair:places>\n";

				$output .= "</accessibility>\n";
			}

			// fee
			if ($response['fee'])
				$output .= "<fee>".$response['fee']."</fee>\n";

			// capacity
			if ($response['capacity'])
				$output .= "<capacity>".$response['capacity']."</capacity>\n";

			// ref
			if ($response['ref'])
				$output .= "<ref>".$response['ref']."</ref>\n";

			// internet access
			if ($response['internet_access'])
				$output .= "<internet_access>".$response['internet_access']."</internet_access>\n";

			// toll
			if ($response['toll'] == "yes")
				$output .= "<toll />\n";

			// disused
			if ($response['disused'] == "yes")
				$output .= "<disused />\n";

			// wikipedia
			if ($wikipedia)
			{
				$output .= "<wikipedia>\n";
					$output .= "<url>".$wikipedia[1]."</url>\n";
					// request first lines
					$output .= "<text>".getWikipediaBeginning($wikipedia[1])."</text>\n";
				$output .= "</wikipedia>\n";
			}

			// image, only images from domains listed on a whitelist are supported
			if (imageDomainAllowed($response['image']))
			{
				$url = getImageUrl($response['image']);
				$output .= "<image>";
 					$output .= $url;
				$output .= "</image>\n";
			}
			else if (getWikipediaImage($wikipedia[1]))
			{
				$image = getWikipediaImage($wikipedia[1]);

				$output .= "<image>";
					$output .= $image;
				$output .= "</image>\n";
			}

			$output .= "</moredetails>";

			return $output;
		}

		else
			return false;
	}


	// output of details data in json format
	function jsonMoreDetailsOut($response, $nameresponse, $wikipediaresponse, $langs = "en", $offset = 0, $id, $type, $callback)
	{
		if ($response)
		{
			$name = getNameDetail($langs, $nameresponse);

			$phone = getPhoneFaxDetail(array($response['phone1'], $response['phone2'], $response['phone3']));
			$fax = getPhoneFaxDetail(array($response['fax1'], $response['fax2'], $response['fax3']));
			$mobilephone = getPhoneFaxDetail(array($response['mobilephone1'], $response['mobilephone2']));
			$website = getWebsiteDetail(array($response['website1'], $response['website2'], $response['website3'], $response['website4']));
			$email = getMailDetail(array($response['email1'], $response['email2'], $response['email3']));

			// get wikipedia link and make translation
			if ($wikipediaresponse)
				$wikipedia = getWikipediaDetail($langs, $wikipediaresponse);

			$openinghours = getOpeninghoursDetail($response['openinghours']);
			$servicetimes = getOpeninghoursDetail($response['servicetimes']);

			$data = array(
				'id' => (int)$id,
				'type' => $type,
			);

			// name
			if ($name)
			{
				if ($name[0])
					$data['name'] = array('lang' => $name[1], 'name' => $name[0]);
				else
					$data['name'] = $name[0];
			}

			 if ($response['description'])
				$data['description'] = $response['description'];

			// address information
			if ($response['street'])
				$data['street'] = $response['street'];
			if ($response['housenumber'])
				$data['housenumber'] = $response['housenumber'];
			if ($response['country'])
				$data['country'] = strtoupper($response['country']);
			if ($response['postcode'])
				$data['postcode'] = $response['postcode'];
			if ($response['city'])
				$data['city'] = $response['city'];
			if ($response['suburb'])
				$data['suburb'] = $response['suburb'];

			// contact information
			if ($phone)
			{
				$tmp = array();
				foreach($phone as $phonenumber)
					array_push($tmp, $phonenumber[1]);
				$data['phone'] = $tmp;
			}
			if ($fax)
			{
				$tmp = array();
				foreach($fax as $faxnumber)
					array_push($tmp, $faxnumber[1]);
				$data['fax'] = $tmp;
			}
			if ($mobilephone)
			{
				$tmp = array();
				foreach($mobilephone as $mobilephonenumber)
					array_push($tmp, $mobilephonenumber[1]);
				$data['mobilephone'] = $tmp;
			}
			if ($email)
				$data['email'] = $email;

			// website link
			if ($website)
			{
				$tmp = array();
				foreach($website as $webaddress)
					array_push($tmp, $webaddress[0]);
				$data['website'] = $tmp;
			}

			// operator
			if ($response['operator'])
				$data['operator'] = $response['operator'];

			// opening hours
			if ($openinghours)
			{
				if (isPoiOpen($response['openinghours'], $offset))
					$state .= "open";
				else if (isInHoliday($response['openinghours'], $offset))
					$state .= "maybeopen";
				else
					$state .= "closed";

				$data['openinghours'] = array('state' => $state, 'openinghours' => $response['openinghours']);
			}

			// service times
			if ($servicetimes)
			{
				if (isPoiOpen($response['servicetimes'], $offset))
					$state .= "open";
				else if (isInHoliday($response['servicetimes'], $offset))
					$state .= "maybeopen";
				else
					$state .= "closed";

				$data['servicetimes'] = array('state' => $state, 'servicetimes' => $response['servicetimes']);
			}

			// fuel details
			if ($response['carwash'] || $response['carrepair'] || $response['kiosk'] || ($response['diesel'] == "yes") || ($response['gtldiesel'] == "yes") || ($response['hgvdiesel'] == "yes") || ($response['biodiesel'] == "yes") || ($response['octane91'] == "yes") || ($response['octane95'] == "yes") || ($response['octane98'] == "yes") || ($response['octane100'] == "yes") || ($response['octane98l'] == "yes") || ($response['fuel25'] == "yes") || ($response['fuel50'] == "yes") || ($response['alcohol'] == "yes") || ($response['ethanol'] == "yes") || ($response['methanol'] == "yes") || ($response['svo'] == "yes") || ($response['e85'] == "yes") || ($response['biogas'] == "yes") || ($response['lpg'] == "yes") || ($response['cng'] == "yes") || ($response['lh2'] == "yes") || ($response['electro'] == "yes") || ($response['adblue'] == "yes"))
			{
				$data['fuel'] = array();
				// fuel sorts
				if ($response['diesel'] == "yes")
					array_push($data['fuel'], "diesel");
				if ($response['gtldiesel'] == "yes")
					array_push($data['fuel'], "gtldiesel");
				if ($response['hgvdiesel'] == "yes")
					array_push($data['fuel'], "hgvdiesel");
				if ($response['biodiesel'] == "yes")
					array_push($data['fuel'], "biodiesel");
				if ($response['octane91'] == "yes")
					array_push($data['fuel'], "octane91");
				if ($response['octane95'] == "yes")
					array_push($data['fuel'], "octane95");
				if ($response['octane98'] == "yes")
					array_push($data['fuel'], "octane98");
				if ($response['octane100'] == "yes")
					array_push($data['fuel'], "octane100");
				if ($response['octane98l'] == "yes")
					array_push($data['fuel'], "octane98l");
				if ($response['fuel25'] == "yes")
					array_push($data['fuel'], "1:25");
				if ($response['fuel50'] == "yes")
					array_push($data['fuel'], "1:50");
				if ($response['alcohol'] == "yes")
					array_push($data['fuel'], "alcohol");
				if ($response['ethanol'] == "yes")
					array_push($data['fuel'], "ethanol");
				if ($response['methanol'] == "yes")
					array_push($data['fuel'], "methanol");
				if ($response['svo'] == "yes")
					array_push($data['fuel'], "svo");
				if ($response['e10'] == "yes")
					array_push($data['fuel'], "e10");
				if ($response['e85'] == "yes")
					array_push($data['fuel'], "e85");
				if ($response['biogas'] == "yes")
					array_push($data['fuel'], "biogas");
				if ($response['lpg'] == "yes")
					array_push($data['fuel'], "lpg");
				if ($response['cng'] == "yes")
					array_push($data['fuel'], "cng");
				if ($response['lh2'] == "yes")
					array_push($data['fuel'], "lh2");
				if ($response['electro'] == "yes")
					array_push($data['fuel'], "electricity");
				if ($response['adblue'] == "yes")
					array_push($data['fuel'], "adblue");
				// other properties of fuel station
				if ($response['carwash'] == "yes")
					array_push($data['fuel'], "carwash");
				if ($response['carrepair'] == "yes")
					array_push($data['fuel'], "carrepair");
				if ($response['shop'] == "kiosk" || $response['kiosk'] == "yes")
					array_push($data['fuel'], "kiosk");
			}

			// gastro
			if ($response['cuisine'] || $response['stars'] || $response['smoking'] || $response['microbrewery'] || $response['beer'])
			{
				// cuisine
				if ($response['cuisine'])
					$data['cuisine'] = explode(";", $response['cuisine']);
				// stars
				if ($response['stars'])
					$data['stars'] = $response['stars'];
				// smoking
				if ($response['smoking'])
					$data['smoking'] = $response['smoking'];
				// beer sorts
				if ($response['beer'])
					$data['beer'] = str_replace(";", ",", $response['beer']);
				// microbrewery
				if ($response['microbrewery'] == "yes")
					$data['microbrewery'] = "yes";
				// biergarten
				if (($response['biergarten'] == "yes") || ($response['beer_garden'] == "yes"))
					$data['biergarten'] = "yes";
			}

			// geographic
			if ($response['ele'] || $response['population'] || $response['iata'] || $response['icao'])
			{
				if ($response['ele'])
					$data['ele'] = $response['ele'];
				if ($response['population'])
					$data['population'] = $response['population'];
				if ($response['iata'])
					$data['iata'] = $response['iata'];
				if ($response['icao'])
					$data['icao'] = $response['icao'];
			}

			// wheelchair
			if ($response['wheelchair'] || $response['wheelchair:toilets'] || $response['wheelchair:rooms'] || $response['wheelchair:access'] || $response['wheelchair:places'])
			{
				if ($response['wheelchair'])
					$data['wheelchair'] = $response['wheelchair'];
				if ($response['wheelchair:toilets'])
					$data['wheelchair:toilets'] = $response['wheelchair:toilets'];
				if ($response['wheelchair:rooms'])
					$data['wheelchair:rooms'] = $response['wheelchair:rooms'];
				if ($response['wheelchair:access'])
					$data['wheelchair:access'] = $response['wheelchair:access'];
				if ($response['wheelchair:places'])
					$data['wheelchair:places'] = $response['wheelchair:places'];
			}

			// fee
			if ($response['fee'])
				$data['fee'] = $response['fee'];

			// capacity
			if ($response['capacity'])
				$data['capacity'] = $response['capacity'];

			// ref
			if ($response['ref'])
				$data['ref'] = $response['ref'];

			// internet access
			if ($response['internet_access'])
				$data['internet_access'] = $response['internet_access'];

			// toll
			if ($response['toll'] == "yes")
				$data['toll'] = "yes";

			// disused
			if ($response['disused'] == "yes")
				$data['disused'] = "yes";

			// wikipedia
			if ($wikipedia)
				$data['wikipedia'] = array('url' => $wikipedia[1], 'text' => getWikipediaBeginning($wikipedia[1]));

			// image, only images from domains listed on a whitelist are supported
			if (imageDomainAllowed($response['image']))
				$data['image'] = getImageUrl($response['image']);
			else if (getWikipediaImage($wikipedia[1]))
				$data['image'] = getWikipediaImage($wikipedia[1]);

			$jsonData = json_encode($data);
			// JSONP request?
			if (isset($callback))
				return $callback.'('.$jsonData.')';
			else
				return $jsonData;
		}

		else
			return false;
	}
?>
