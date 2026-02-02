#!/usr/bin/perl

use strict;
use warnings;
use DBI;

# --- ENV proměnné ---
my $addr          = $ENV{'REMOTE_ADDR'};
my $host          = $ENV{'REMOTE_HOST'};
my $query_string  = $ENV{'QUERY_STRING'};
my $document_uri  = $ENV{'DOCUMENT_URI'};
my $document_root = $ENV{'DOCUMENT_ROOT'};
my $http_referer  = $ENV{'HTTP_REFERER'};
my $forwarded     = $ENV{'HTTP_X_FORWARDED_FOR'};

# --- Bezpečnější čtení .dbpass ---
my $dbpass = "";
if (open my $fh, '<', '/home/hujer/.dbpass') {
    my $line = <$fh>;
    close $fh;
    (undef, $dbpass, undef) = split(':', $line);
}

# --- Připojení k DB ---
my $dbh = DBI->connect(
    'DBI:mysql:hujer_net:localhost:3306',
    'master',
    $dbpass,
    { RaiseError => 1, PrintError => 0 }
);

# --- SQL dotaz ---
my $sth = $dbh->prepare(qq{
    select teplota1, tlak, vlhkost, batery, time, date, mpsas
    from teploty
    where timesec = (
        select max(timesec) from teploty where sensor = 'SQM-HR03'
    )
});
$sth->execute;

my ($teplota, $tlak, $vlhkost, $batery, $cas_t, $datum_t, $sqm)
    = $sth->fetchrow_array;

$dbh->disconnect;

# --- Výpočet tlaku ---
my $tlak_m = sprintf("%.2f", $tlak + 600/8.3);

# --- Logika pro články ---
my ($document_pwd, $nazev_prirucky, $nadpis, $referer_text);

if ($query_string eq "") {
    $document_pwd    = substr($document_uri, 1, rindex($document_uri, '/'));
    $nazev_prirucky  = $document_root . '/myblog.txt';
    $http_referer    = "/";
    $referer_text    = "Zpět na hlavní stránku";
    $nadpis          = $document_root . '/nadpis.txt';
} else {
    $document_pwd    = substr($query_string, 0, rindex($query_string, '/'));
    $nazev_prirucky  = $query_string . '.html';
    $referer_text    = "Zpět";
    $nadpis          = $document_root . '/nadpis.txt';
}

# --- IP adresa ---
my $xhost = ($forwarded ne "")
    ? substr("$forwarded,", 0, index("$forwarded,", ","))
    : $host;

# --- Datum ---
my @mesic = ('leden','únor','březen','duben','květen','červen','červenec','srpen','září','říjen','listopad','prosinec');
my $now = time();
my $tnow = $now + 86400;

my ($sek,$min,$hod,$den,$mes,$rok) = localtime($now);
my ($Tsek,$Tmin,$Thod,$Tden,$Tmes,$Trok) = localtime($tnow);

$rok += 1900;
$min = '0'.$min if $min < 10;

my $date  = "$den. $mesic[$mes] $rok";
my $time  = "$hod:$min";
my $Tdate = "$Tden. $mesic[$Tmes] $Trok";
my $den_jm = ("neděle","pondělí","úterý","středa","čtvrtek","pátek","sobota")[(localtime)[6]];

$mes++;
$Tmes++;

# --- HTTP hlavička ---
print "Content-type: text/html\n\n";

# --- HTML začátek ---
print <<END_HTML;
<html>
<head>
<title>Počasí Hujer.net</title>
<meta charset="utf-8">
</head>
<body bgcolor="#f8f4f1">

<br><br><br>
<font face="Times" size="6" color="blue"><b>POČASÍ HUJER.NET</b></font>
<br><br><br>

END_HTML

# --- Nadpis ---
open(my $NF, "<", $nadpis) or print "Soubor <b>$nadpis</b> nebyl nalezen!";
print <$NF>;
close($NF);

print <<END_HTML;
<br><br>
END_HTML

# --- Pravý panel (jen na hlavní stránce) ---
if ($query_string eq "") {

print <<END_HTML;
<td width="200" rowspan="6" bgcolor="#f8f4f1" valign="top">

<table width="200" cellspacing="0" cellpadding="2">
<tr><td bgcolor="#17116a" align="center">
<font size="1" color="#ffffff">A K T U Á L N Í &nbsp; P O Č A S Í</font>
</td></tr>

<tr><td><center>
<img src="teplomer.png"><br>
<h3>Nový sensor</h3>
<b>Teplota: $teplota °C</b><br>
<b>Vlhkost: $vlhkost %</b><br>
<b>Tlak: $tlak_m ($tlak) hPa</b><br>
<b>SQM: $sqm mag/arcsec²</b><br>
<b>Odečet: $cas_t $datum_t</b>
</center></td></tr>

<tr><td><center><b>Předpověď</b></center></td></tr>

<tr><td><center>
<img src="https://www.meteopress.cz/pictures/pp_cr_0den.png"><p>
<img src="https://www.meteopress.cz/pictures/pp_cr_1den.png"><p>
<img src="https://www.meteopress.cz/pictures/pp_cr_2den.png"><p>
<img src="https://www.meteopress.cz/pictures/pp_cr_3den.png">
</center></td></tr>

</table>
</td>
END_HTML

}

# --- Odkazy (jen na hlavní stránce) ---
if ($query_string eq "") {

print <<END_HTML;
<td width="140" valign="top" bgcolor="#9999cc">
END_HTML

open(my $OF, "<", "odkazy.txt") or print "Soubor <b>odkazy.txt</b> nebyl nalezen!";
print <$OF>;
close($OF);

print "</td>";

} else {
    print "<tr>";
}

# --- Článek / hlavní stránka ---
print <<END_HTML;
<td width="700" valign="top" bgcolor="white">
<div>
END_HTML

open(my $PF, "<", $nazev_prirucky) or print "Článek <b>$nazev_prirucky</b> nebyl nalezen!";
print <$PF>;
close($PF);

print <<END_HTML;
</div>
<br><br>
<a href="$http_referer">$referer_text</a>
</td>
</tr>
</table>

</body>
</html>
END_HTML

exit;

# --- Funkce svátek ---
sub svatek {
    my %sv = (
        "1.1.","",
        "2.1.","Karina",
        "3.1.","Radmila",
        "4.1.","Diana",
        "5.1.","Dalimil",
        "6.1.","",
        "7.1.","Vilma",
        "8.1.","Čestmír",
        "9.1.","Vladan",
        "10.1.","Břetislav",
        ...
        "31.12.","Silvester"
    );
    return $sv{$_[0]};
}

# --- Funkce narozeniny ---
sub narozeniny {
    my %sv = (
        "16.1.","Vojtěch Hujer:2006",
        "18.1.","Jiří Černohorký:2007",
        "29.1.","Lucie Černohorská:1980",
        "14.2.","Jan Hujer:1973",
        ...
        "20.9.","Ota Hujer:1941"
    );
    return $sv{$_[0]};
}
