<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>toPDO Converter</title>
	<script src="//code.jquery.com/jquery-3.4.1.min.js"></script>
	<style>
		body{
			font-family: Arial;
		}
		table{
			width: 100%;
		}

		td{
			padding:10px;
		}

		textarea{
			width: 100%;
			min-height: 500px;
			padding:5px;
		}

	</style>
</head>
<body>
	<h1>TWM to PDO</h1>
	<p><strong>Dieser Script wandelt alten TWM Database Code in neuen PDO Code</strong></p>


	<table>
		<thead>
			<tr>
				<th>Alter Code</th>
				<th>Neuer Code</th>
			</tr>
		</thead>
		<tbody>
			<tr>
				
				<td class="input">
					<textarea  id="input">
$sql = "INSERT INTO twm_users_locks SET
locked_until = DATE_ADD(NOW(), INTERVAL {$this->lockTime} SECOND),
id_users = " . $id_users . " AND
ip = '{$ip}'
ON DUPLICATE KEY UPDATE locked_until = DATE_ADD(NOW(), INTERVAL {$this->lockTime} SECOND)
";

$this->Core->toDatabase($sql);
					</textarea>
				</td>

				<td class="output">
					<textarea  id="output"></textarea>
				</td>

			</tr>
		</tbody>
	</table>



	<h3>Anleitung</h3>

	<p>Code einfügen: Es muss mindestens ein Code mit der SQL-Query sein. Zeile mit toDatabase / fromDatabase sind freiwillig, diese werden aber ebenfalls umgeschrieben.</p>

	<h3>Wie funktioniert das?</h3>
	<p>
		Der Umwandlungsprozess sieht so aus:
		<ul>
			<li>Code in Parser einlesen (Library: nikic/php-parser)</li>
			<li>Bei Strings werden alle inkludierten Variablen rausgeparst und durch Platzhalter ersetzt, dabei wird ein Param-Array gebildet</li>
			<li>Die SQL Parameter (Array) wird als eine eigene Variable generiert.</li>
			<li>Die fromDatabase / toDatabase wird durch die DB:: Methoden ersetzt.</li>
			<li>Der generierte Code wird getestet: Die SQL Query wird mit EXPLAIN ausgeführt. Die Datenbank für die Tests ist die aktuelle TWM Datenbank. Sollte der Generator einen Fehlerhaften Code generieren, wird das im rechten Fenster gezeigt. Fehler treten auf wenn z.B. die SQL Syntax falsch ist, Columns nicht gefunden wurden oder der PHP Code einen Fehler enthielt</li>
			<li>Ist der Code in Ordnung, wird dieser rechts ins Textarea eingefügt</li>
		</ul>
	</p>

	<h3>Einschränkungen</h3>
	<p>
		<ul>
			<li>Es wandelt noch keine one-liner um, Beispiel: $x = fromDatabase("SELECT * FROM...", ...) - Die Query muss in eigener Variable stecken, z.B. $sql (Name ist egal)</li>
			<li>Keine Prüfung von SQL Parametern, beim Testen haben alle Parameter den Wert 0.</li>
			<li>Es wurden viele Fälle getestet, es könnte aber noch sein dass etwas nicht richtig funzt, bitte immer den Code in AUgenschein nehmen und nicht blind darauf vertrauen dass es schon funktionieren wird. Wir haben leider keine Unit Tests, deshalb immer schauen, besonders bei komplexen Queries.</li>
		</ul>
	</p>

	<h3>Tipps</h3>
	<ul>
		<li>Wer es schneller haben will, kann sich mal das AutoHotkey Makro anschauen <a href="convert_to_pdo.ahk">convert_to_pdo.ahk</a>. Damit kann man einen Text im Codeeditor markieren, eine Tastenkombination drücken und die Auswahl wird sofort durch den neuen Code ersetzt. Wer es will, sollte sich dieses Projekt aber lokal <a href="https://github.com/millsoft/topdo" target="_blank">klonen</a>, da das Makro nur eine input-Datei einließt.</li>
	</ul>


	<hr/>
	<p>2019 by Michel</p>

	<script>

		function parseCode(){
				var params = {
					"input" : $("#input").val()
				};

				$.post("web_convert_ajax.php", params, function(re){
					$("#output").val(re);
				});			
		}

		$(function(){
			$("#input").keyup(function(){
				parseCode();
			});

			parseCode();
		});
	</script>
</body>
</html>