# test.php
### Usage

to run:

	php test.php [search-string]


Runs through all files in `inputs`, compiles them, then
compares to respective file in `outputs`. If there are
any differences then the test will fail.

If you run the script with a search string, it will only
run the tests that contain that substring.

Add the -d flag to show the differences of failed tests
in your diff tool (currently assigned in code, `$difftool`)
Defaults to diff, but I like using meld.

Pass the -C flag to save the output of the inputs to
the appropriate file. This will overwrite any existing
outputs. Use this when you want to save verified test
results.


