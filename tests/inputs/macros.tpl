
Defining macros:

%father={How are you doing?}%
%mother = { 
	Doing great
}%

%son=[["who <strong>goes</strong> there" here is some junk: }}}}}} ]  [[ { } %{  }% ]]

%html = ($body) {
	<!DOCTYPE HTML>
	<html lang="en">
	<head>
		<meta charset="UTF-8">
		<title></title>
	</head>
	<body>
	  $body		
	</body>
	</html>
}%


%box = ($title, $body) [[
	<div class="rounded">
		<div class="border">
			<h2>$title</h2>
		</div>
		<p>$body</p>
	</div>
]]


Calling macros:

%father%

%html [[
	%box("A dialog") [[
		<ul>
			<li>Father: %father%</li>
			<li>Mother: %mother%</li>
		</ul>
	]]

	%box("Footer") {
		ignored this: %son%
	}%
]]


