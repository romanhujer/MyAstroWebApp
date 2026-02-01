#!/usr/bin/perl
#
# Copyright (c) 1998 - 2018 Roman Hujer 
#


use DBI; 

$addr = $ENV{'REMOTE_ADDR'};
$host = $ENV{'REMOTE_HOST'};

$query_string =  $ENV{'QUERY_STRING'};
$document_uri =  $ENV{'DOCUMENT_URI'};
$document_root = $ENV{'DOCUMENT_ROOT'};
$http_referer  = $ENV{'HTTP_REFERER'};
$forwarded = $ENV{'HTTP_X_FORWARDED_FOR'};
($_x,$dbpass,$_x) = split(':', `/usr/bin/cat /home/hujer/.dbpass`);  

$dbh = DBI->connect('DBI:mysql:hujer_net:localhost:3306',
                      'master', $dbpass ) ||  die "Can't connect : $DBI::errstr";
	
$sth = $dbh->prepare( <<END_SQL) ||  die $DBI::errstr;
select teplota1, tlak, vlhkost, batery, time, date, mpsas
 from teploty 
 where   timesec  = (select max(timesec) from  teploty where sensor = 'SQM-HR03' );

END_SQL

	$rc = $sth->execute  ||  die $DBI::errstr ;

	( $teplota, $tlak, $vlhkost, $batery, $cas_t, $datum_t, $sqm ) = $sth->fetchrow_array;  

$dbh->disconnect; 

$tlak_m = sprintf ( "%.2f", $tlak + 600/8.3);



if ( $query_string eq "" ) {
   $document_pwd = substr($document_uri,1, rindex( $document_uri,'/'));
   $nazev_prirucky= $document_root . '/' . 'myblog.txt';
   $http_referer  = "/";
   $referer_text  = "Zpět na hlavni stránku";
   $nadpis = $document_root . '/' .'nadpis.txt';
   

}
else {
   $document_pwd = substr($query_string,0, rindex( $query_string,'/'));
   $nadpis = $document_root . '/' . 'nadpis.txt';
 	
   $nazev_prirucky =  $query_string . '.html' ;
   $referer_text  = "Zpět"
}


if ( $forwarded ne "" )
{
   $xhost = substr("$forwarded,",0,index("$forwarded,",","));
}
else
{
   $xhost = $host;
}

# Nastveni aktulaniho datumu
@mesic = ('leden','únor','březen','duben','květen','červen','červenec','srpen','září','říjen','listopad','prosinec');
$now=time();
$tnow = $now+86400;
($sek,$min,$hod,$den,$mes,$rok) = localtime($now);
($Tsek,$Tmin,$Thod,$Tden,$Tmes,$Trok) = localtime($tnow);
$rok += 1900;

$date = "$den. $mesic[$mes] $rok ";
$hdate = "$den. $mesic[$mes] $rok v $hod: $min" ;

$min = '0'.$min if ($min<10);
$time = "$hod:$min";
$Tdate = "$Tden. $mesic[$Tmes] $Trok";
$den_jm = ("neděle", "pondělí", "úterý", "středa", "čtvrtek", "pátek", "sobota")[(localtime)[6]];
$mes++;
$Tmes++;



print  "Content-Type: text/html\n\n";

print <<END_HTML;
<!doctype HTML PUBLIC "-//W3C//DTD HTML 3.2 Final//EN">
<html>
<head>
   <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
   <meta http-equiv="Cache-Control" content="no-cache">
   <title>Hujer.Net</title>
</head>

END_HTML

print <<END_HTML;

<body BGCOLOR="#408080" TEXT="Black" LINK="#002800" VLINK="#002800" ALINK="Red">

<table  width="1000" BORDER="0" CELLSPACING="0" CELLPADDING="0">
<tr>
<td bgcolor="white" valign="top">
  <table  border="0" cellspacing="0" cellpadding="0">
  <tr>
	 <td width="30" align="right">&nbsp;	</td>
   <td width="10">&nbsp; </td>
	 <td width="1000" valign="center">
	 <table width="1000" BORDER="0" CELLSPACING="0" CELLPADDING="0">
	 <tr>
		<td valign="top">
		 <a href="https://hujer.net">      
           <img src="roman7.jpg" width=100 border=0></a>
		<br> 
<div>


		</b></font>
</div>		
		</td>
		<td align="middle" valign="top" >

END_HTML


$svatek_dnes = &svatek($den.".".$mes.".");
$svatek_zitra = &svatek($Tden.".".$Tmes.".");
print "Dnes je <B>$den_jm $date</B>";
if ($svatek_dnes){
  print ", svátek má&nbsp;<B>$svatek_dnes</B>";
}
if ($svatek_zitra){
  print ", zítra má svátek&nbsp;<B>$svatek_zitra</B>";
}


$narozeniny_dnes = &narozeniny($den.".".$mes.".");
$narozeniny_zitra = &narozeniny($Tden.".".$Tmes.".");
if ($narozeniny_dnes){
  ($jmeno, $rok_n ) = split /\:/, $narozeniny_dnes;
  $vek = $rok - $rok_n;
  print "<br>Dnes má <b>$vek</b> narozeniny <font color=red><b>$jmeno</b></font>";
}
if ($narozeniny_zitra){
  ($jmeno, $rok_n ) = split /\:/, $narozeniny_zitra;
  $vek = $rok - $rok_n;
  print "<br>Zítra má <b>$vek</b> narozeniny <b>$jmeno</b>";
}
 






print <<END_HTML;
<br>
<br>
<br>

            <font face="Times" size="6" color="blue"> <b>POČASÍ HUJER.NET</b>  </font>
<br>
<br>
<br>
            
		</td>
	  </tr>
	  </table>
	  </td>
	    <td>&nbsp; </td>
	    <td>&nbsp; </td>
 	 </tr>
	 <tr>
	   <td COLSPAN="2">&nbsp;</td>
	   <td ALIGN="CENTER" BGCOLOR="#F8F4F1">
		<p STYLE="text-decoration: none">
		<font FACE="Tahoma, Arial, Helvetica" SIZE="2">
		<b>

END_HTML


open (FILE, "$nadpis" ) || print "Soubor: <b>$nadpis</b> nebyl nalezen !";
print (<FILE>);
close (FILE);


print <<END_HTML;
	      
    </b>
    </font></p>
   </td>
   <td>&nbsp;</td>
   </tr>
   <tr> 
   <td> &nbsp; </td> 
   </tr>
   </table>
 </td>

END_HTML


if ( $query_string eq "" ) {

print <<END_HTML;


<td width="200" rowspan="6" bgcolor="#f8f4f1" valign="top">

   <table WIDTH="200" HEIGHT="10" BORDER="0" CELLSPACING="0" CELLPADDING="0">
   	<tr>
            <td WIDTH="110" BGCOLOR="white">&nbsp;</td>
            <td WIDTH="90" BGCOLOR="#408080">&nbsp;</td>
   </tr>
   </table>			
   <table width="200" border="0" cellspacing="0" cellpadding="2">
    <tr>
	   <td width= "200" bgcolor="#17116a" align="middle">
       <font size="1" face="Arial, Helvetica" color="#ffffff">A K T U Á L N Í &nbsp; P O Č A S Í</font> 
    </td>
    </tr>
  	<tr>
       <td> &nbsp; </td>
     </tr> 
     <tr><td> 
          <center>
            <img src="teplomer.png"><br>

    	    <font size="3"> <b><BR><u>Nový sensor</u></b><br></font>
	    <font size="2"> <b>Teplota: $teplota °C</b><br> </font>
	    <font size="2"> <b>Vlhkost: $vlhkost % </b><br> </font>
    	    <font size="2"> <b>Tlak: $tlak_m ($tlak) hPa </b><br> </font>
    	    <font size="2"> <b>SQM: $sqm m/asˆ2 </b><br> </font>
            <font size="2"> <b>Odečet: $cas_t $datum_t</b> </font>
        </center>
     </td></tr>
<!--
  <tr><td> 
<center>
<br>
<font size="2">
  <a HREF="my.cgi?teplota_out"><b>Průběh teploty na Vrkoslavicích</b></a>
</font>
<br>
  <a HREF="my.cgi?teplota_out"><img BORDER=1  SRC="teplota_out-day.png" width=192></a>
</center>      
  </td></tr>
-->
  <tr><td> 
      &nbsp;
  </td></tr>
  <tr><td> 
<center>
  <b>Předpověd</b>
</center>      
  </td></tr>
  <tr><td>
<center>
<div>
	<img src="https://www.meteopress.cz/pictures/pp_cr_0den.png" alt="" border="0"><p>
	<img src="https://www.meteopress.cz/pictures/pp_cr_1den.png" alt="" border="0"><p>
	<img src="https://www.meteopress.cz/pictures/pp_cr_2den.png" alt="" border="0"><p>
	<img src="https://www.meteopress.cz/pictures/pp_cr_3den.png" alt="" border="0">
</div> 
</center>
 </td></tr>
  	<tr>
       <td> &nbsp; </td>
     </tr> 
  	<tr>
       <td> &nbsp; </td>
     </tr> 

  <tr><td> 
</table>
</td>
</tr>
END_HTML

print <<END_HTML;

<tr>
  
<td width="1050" bgcolor="white" valign="top">

   <table WIDTH="1050" HEIGHT="10" bgcolor="white" BORDER="0" CELLSPACING="0" CELLPADDING="0">
     <tr>
 
	   <td width="200" valign="top" bgcolor="silver"><font size="2" face="Arial, Helvetic">
		 <center><b>Odkazy</b></center></font>
     </td>
     <td  height="20" colspan="4" bgcolor="white">&nbsp; </td>  
     <td  WIDTH="10" bgcolor="white">&nbsp; </td>
     </tr>
	   <tr>
	   <td width="140" align="center" valign="top" bgcolor="#9999cc"><font size="2" face="Arial, Helvetic">

END_HTML

open (FILE, "odkazy.txt") || print "Soubor: <b>odkazy.txt</b> nebyl nalezen !";
print (<FILE>);
close (FILE);

print <<END_HTML;

	</font>

  </td>
  <td WIDTH="10" BGCOLOR="WHITE">&nbsp;</td>

END_HTML

} else {

print "<tr>";

}

print <<END_HTML;
 
   <td width="700" VALIGN="TOP" BGCOLOR="WHITE">
  	 <font FACE="Tahoma, Arial CE, Arial, Helvetica CE, Helvetica" SIZE="2">
<div>
END_HTML

open (FILE, "$nazev_prirucky") || print "Článek : <b>$nazev_prirucky</b> nebyl nalezen !";
print (<FILE>);
close (FILE);

print <<END_HTML;
</div>
<br>&nbsp;
<br>
<a href="$http_referer">$referer_text</a>
        </font>
    </td>
</tr>
</table>
END_HTML


print <<END_HTML;

</body>
</html>

END_HTML


exit;

sub svatek {
%sv=("1.1.","",
	    "2.1.","Karina",
	    "3.1.","Radmila",
	    "4.1.","Diana",
	    "5.1.","Dalimil",
	    "6.1.","",
	    "7.1.","Vilma",
	    "8.1.","Čestmír",
	    "9.1.","Vladan",
	    "10.1.","Břetislav",
	    "11.1.","Bohdana",
	    "12.1.","Pravoslav",
	    "13.1.","Edita",
	    "14.1.","Radovan",
	    "15.1.","Alice",
	    "16.1.","Ctirad",
	    "17.1.","Drahoslav",
	    "18.1.","Vladislav",
	    "19.1.","Doubravka",
	    "20.1.","Ilona",
	    "21.1.","Běla",
	    "22.1.","Slavomír",
	    "23.1.","Zdeněk",
	    "24.1.","Milena",
	    "25.1.","Miloš",
	    "26.1.","Zora",
	    "27.1.","Ingrid",
	    "28.1.","Otýlie",
	    "29.1.","Zdislava",
	    "30.1.","Robin",
	    "31.1.","Marika",
	    "1.2.","Hynek",
	    "2.2.","Nela",
	    "3.2.","Blažej",
	    "4.2.","Jarmila",
	    "5.2.","Dobromila",
	    "6.2.","Vanda",
	    "7.2.","Veronika",
	    "8.2.","Milada",
	    "9.2.","Apolena",
	    "10.2.","Mojmír",
	    "11.2.","Božena",
	    "12.2.","Slavěna",
	    "13.2.","Věnceslav",
	    "14.2.","Valentýn",
	    "15.2.","Jiřina",
	    "16.2.","Ljuba",
	    "17.2.","Miloslava",
	    "18.2.","Gizela",
	    "19.2.","Patrik",
	    "20.2.","Oldřich",
	    "21.2.","Lenka",
	    "22.2.","Petr",
	    "23.2.","Svatopluk",
	    "24.2.","Matěj",
	    "25.2.","Liliana",
	    "26.2.","Dora",
	    "27.2.","Alexandr",
	    "28.2.","Lumír",
	    "29.2.","Horymír",
	    "1.3.","Bedřich",
	    "2.3.","Anežka",
	    "3.3.","Kamil",
	    "4.3.","Stela",
	    "5.3.","Kazimír",
	    "6.3.","Miroslav",
	    "7.3.","Tomáš",
	    "8.3.","Gabriela",
	    "9.3.","Františka",
	    "10.3.","Viktorie",
	    "11.3.","Anděla",
	    "12.3.","Řehoř",
	    "13.3.","Růžena",
	    "14.3.","Rút",
	    "15.3.","Ida",
	    "16.3.","Elena",
	    "17.3.","Vlastimil",
	    "18.3.","Eduard",
	    "19.3.","Josef",
	    "20.3.","Světlana",
	    "21.3.","Radek",
	    "22.3.","<font color=red>Ota</font>/Leona",
	    "23.3.","Ivona",
	    "24.3.","Gabriel",
	    "25.3.","Marián",
	    "26.3.","Emanuel",
	    "27.3.","Dita",
	    "28.3.","Soňa",
	    "29.3.","Taťána",
	    "30.3.","Arnošt",
	    "31.3.","Kvido",
	    "1.4.","Hugo",
	    "2.4.","Erika",
	    "3.4.","Richard",
	    "4.4.","<font color=red>Ivana</font>",
	    "5.4.","Miroslava",
	    "6.4.","<font color=red>Vendula</font>",
	    "7.4.","Heřman",
	    "8.4.","Ema",
	    "9.4.","Dušan",
	    "10.4.","Darja",
	    "11.4.","Izabela",
	    "12.4.","Julius",
	    "13.4.","Aleš",
	    "14.4.","Vincenc",
	    "15.4.","Anastázie",
	    "16.4.","Irena",
	    "17.4.","Rudolf",
	    "18.4.","Valérie",
	    "19.4.","Rostislav",
	    "20.4.","Marcela",
	    "21.4.","Alexandra",
	    "22.4.","Evženie",
	    "23.4.","<font color=red>Vojtěch</font>",
	    "24.4.","<font color=red>Jiří</font>",
	    "25.4.","Marek",
	    "26.4.","Oto",
	    "27.4.","Jaroslav",
	    "28.4.","Vlastislav",
	    "29.4.","Robert",
	    "30.4.","Blahoslav",
	    "1.5.","",
	    "2.5.","Zikmund",
	    "3.5.","Alexej",
	    "4.5.","Květoslav",
	    "5.5.","Klaudie",
	    "6.5.","Radoslav",
	    "7.5.","Stanislav",
	    "8.5.","",
	    "9.5.","Ctibor",
	    "10.5.","Blažena",
	    "11.5.","Svatava",
	    "12.5.","Pankrác",
	    "13.5.","Servác",
	    "14.5.","Bonifác",
	    "15.5.","Žofie",
	    "16.5.","<font color=red>Jan</font>/Přemysl",
	    "17.5.","Aneta",
	    "18.5.","Nataša",
	    "19.5.","Ivo",
	    "20.5.","Zbyšek",
	    "21.5.","Monika",
	    "22.5.","Emil",
	    "23.5.","Vladimír",
	    "24.5.","Jana",
	    "25.5.","Viola",
	    "26.5.","Filip",
	    "27.5.","Valdemar",
	    "28.5.","Vilém",
	    "29.5.","Maxim",
	    "30.5.","Ferdinand",
	    "31.5.","Kamila",
	    "1.6.","Laura",
	    "2.6.","Jarmil",
	    "3.6.","Tamara",
	    "4.6.","Dalibor",
	    "5.6.","Dobroslav",
	    "6.6.","Norbert",
	    "7.6.","Iveta",
	    "8.6.","Medard",
	    "9.6.","Stanislava",
	    "10.6.","Gita",
	    "11.6.","Bruno",
	    "12.6.","Antonie",
	    "13.6.","Antonín",
	    "14.6.","Roland",
	    "15.6.","Vít",
	    "16.6.","Zbyněk",
	    "17.6.","Adolf",
	    "18.6.","Milan",
	    "19.6.","Leoš",
	    "20.6.","Květa",
	    "21.6.","Alois",
	    "22.6.","Pavla",
	    "23.6.","Zdeňka",
	    "24.6.","Jan",
	    "25.6.","Ivan",
	    "26.6.","Adriana",
	    "27.6.","Ladislav",
	    "28.6.","Lubomír",
	    "29.6.","Petr a Pavel",
	    "30.6.","Šárka",
	    "1.7.","Jaroslava",
	    "2.7.","Patricie",
	    "3.7.","Radomír",
	    "4.7.","Prokop",
	    "5.7.","Cyril a Metoděj",
	    "6.7.","mistr Jan Hus",
	    "7.7.","Bohuslava",
	    "8.7.","Nora",
	    "9.7.","Drahoslava",
	    "10.7.","Libuše",
	    "11.7.","Olga",
	    "12.7.","Bořek",
	    "13.7.","<font color=red>Markéta</font>",
	    "14.7.","Karolína",
	    "15.7.","Jindřich",
	    "16.7.","Luboš",
	    "17.7.","Martina",
	    "18.7.","Drahomíra",
	    "19.7.","Čeněk",
	    "20.7.","Ilja",
	    "21.7.","Vítězslav",
	    "22.7.","Magdaléna",
	    "23.7.","Libor",
	    "24.7.","Kristýna",
	    "25.7.","Jakub",
	    "26.7.","Anna",
	    "27.7.","Věroslav",
	    "28.7.","Viktor",
	    "29.7.","Marta",
	    "30.7.","Bořivoj",
	    "31.7.","Ignác",
	    "1.8.","Oskar",
	    "2.8.","Gustav",
	    "3.8.","Miluše",
	    "4.8.","Dominik",
	    "5.8.","Kristián",
	    "6.8.","Oldřiška",
	    "7.8.","Lada",
	    "8.8.","Soběslav",
	    "9.8.","<font color=red>Roman</font>",
	    "10.8.","Vavřinec",
	    "11.8.","Zuzana",
	    "12.8.","Klára",
	    "13.8.","Alena",
	    "14.8.","Alan",
	    "15.8.","Hana",
	    "16.8.","Jáchym",
	    "17.8.","Petra",
	    "18.8.","Helena",
	    "19.8.","Ludvík",
	    "20.8.","Bernard",
	    "21.8.","Johana",
	    "22.8.","Bohuslav",
	    "23.8.","Sandra",
	    "24.8.","Bartoloměj",
	    "25.8.","Radim",
	    "26.8.","Luděk",
	    "27.8.","Otakar",
	    "28.8.","Augustýn",
	    "29.8.","Evelína",
	    "30.8.","Vladěna",
	    "31.8.","Pavlína",
	    "1.9.","Linda",
	    "2.9.","Adéla",
	    "3.9.","Bronislav",
	    "4.9.","Jindřiška",
	    "5.9.","Boris",
	    "6.9.","Boleslav",
	    "7.9.","Regina",
	    "8.9.","Mariana",
	    "9.9.","Daniela",
	    "10.9.","Irma",
	    "11.9.","Denisa",
	    "12.9.","Marie",
	    "13.9.","Lubor",
	    "14.9.","Radka",
	    "15.9.","Jolana",
	    "16.9.","Ludmila",
	    "17.9.","Naděžda",
	    "18.9.","Kryštof",
	    "19.9.","Zita",
	    "20.9.","Oleg",
	    "21.9.","Matouš",
	    "22.9.","Darina",
	    "23.9.","Berta",
	    "24.9.","Jaromír",
	    "25.9.","Zlata",
	    "26.9.","Andrea",
	    "27.9.","Jonáš",
	    "28.9.","Václav",
	    "29.9.","Michal",
	    "30.9.","Jeroným",
	    "1.10.","Igor",
	    "2.10.","Olivie/Oliver",
	    "3.10.","Bohumil",
	    "4.10.","František",
	    "5.10.","<font color=red>Eliška</font>",
	    "6.10.","Hanuš",
	    "7.10.","Justýna",
	    "8.10.","Věra",
	    "9.10.","Štefan/Sára",
	    "10.10.","Marina",
	    "11.10.","Andrej",
	    "12.10.","Marcel",
	    "13.10.","Renáta",
	    "14.10.","Agáta",
	    "15.10.","<font color=red>Tereza</font>",
	    "16.10.","Havel",
	    "17.10.","Hedvika",
	    "18.10.","Lukáš",
	    "19.10.","Michaela",
	    "20.10.","Vendelín",
	    "21.10.","Brigita",
	    "22.10.","Sabina",
	    "23.10.","Teodor",
	    "24.10.","Nina",
	    "25.10.","Beáta",
	    "26.10.","Erik",
	    "27.10.","Zoja",
	    "28.10.","",
	    "29.10.","Silvie",
	    "30.10.","Tadeáš",
	    "31.10.","Štěpánka",
	    "1.11.","Felix",
	    "2.11.","",
	    "3.11.","Hubert",
	    "4.11.","Karel",
	    "5.11.","Miriam",
	    "6.11.","Liběna",
	    "7.11.","Saskie",
	    "8.11.","Bohumír",
	    "9.11.","Bohdan",
	    "10.11.","Evžen",
	    "11.11.","Martin",
	    "12.11.","Benedikt",
	    "13.11.","Tibor",
	    "14.11.","Sáva",
	    "15.11.","Leopold",
	    "16.11.","Otmar",
	    "17.11.","Mahulena",
	    "18.11.","Romana",
	    "19.11.","Alžběta",
	    "20.11.","Nikola",
	    "21.11.","Albert",
	    "22.11.","Cecílie",
	    "23.11.","Klement",
	    "24.11.","Emílie",
	    "25.11.","Kateřina",
	    "26.11.","Artur",
	    "27.11.","Xenie",
	    "28.11.","René",
	    "29.11.","Zina",
	    "30.11.","Ondřej",
	    "1.12.","Iva",
	    "2.12.","Blanka",
	    "3.12.","Svatoslav",
	    "4.12.","<font color=red>Barbora</font>",
	    "5.12.","Jitka",
	    "6.12.","Mikuláš",
	    "7.12.","Ambrož",
	    "8.12.","Květoslava",
	    "9.12.","Vratislav",
	    "10.12.","Julie",
	    "11.12.","Dana",
	    "12.12.","Simona",
	    "13.12.","<font color=red>Lucie</font>",
	    "14.12.","Lýdie",
	    "15.12.","Radana",
	    "16.12.","Albína",
	    "17.12.","Daniel",
	    "18.12.","Miloslav",
	    "19.12.","Ester",
	    "20.12.","Dagmar",
	    "21.12.","Natálie",
	    "22.12.","Šimon",
	    "23.12.","Vlasta",
	    "24.12.","Adam a Eva",
	    "25.12.","",
	    "26.12.","Štěpán",
	    "27.12.","Žaneta",
	    "28.12.","Bohumila",
	    "29.12.","Judita",
	    "30.12.","David",
	    "31.12-","Silvester");	
    return($sv{$_[0]});
}

sub narozeniny {
%sv=(
	"16.1.","Vojtěch Hujer:2006",
	"18.1.","Jiří Černohorký:2007",
	"29.1.","Lucie Černohorská:1980",
	"14.2.","Jan Hujer:1973",
	"17.2.","Barbora Černohorská:2010",
	"13.3.","Eliška Hujerová:2001",
	"14.3.","Markéta Hujerová:1972",
	"22.5.","Tereza Hujerová:1998",
	"19.7.","Ivana Hujerová:1947",
	"23.7.","Jan Drábek:1942",
	"31.7.","Vendula Pelantová:2005",
	"7.8.","Roman Hujer:1967",
	"20.9.","Ota Hujer:1941"); 

    return($sv{$_[0]});
}




#!END ;-
