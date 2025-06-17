#!/bin/bash
# This script sets up a CRON job to run cron.php every 24 hours.

# Get the absolute path of the directory where this script is located (the src/ directory)
# This ensures that the cron job can find cron.php regardless of where the setup script is run from.
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# PHP binary
PHP_BIN=$(which php)

# Check if the PHP executable was found
if [ -z "$PHP_BIN" ]; then
    echo "Error: PHP executable not found. Please ensure PHP is installed and in your system's PATH."
    exit 1
fi

# CRON job line - runs every day at 9 AM IST (you can change time if needed)
# Using > /dev/null 2>&1 redirects all output (stdout and stderr) to /dev/null,
# preventing cron from emailing you script output every time it runs.
CRON_JOB="0 9 * * * $PHP_BIN $DIR/cron.php > /dev/null 2>&1"

# Check if the CRON job already exists to avoid adding duplicates.
# 'crontab -l' lists current cron jobs.
# 'grep -Fq "$DIR/cron.php"' searches quietly (-q) for a fixed string (-F) matching your cron.php path.
# '2>/dev/null' redirects any error messages (e.g., if no crontab exists yet) to null.
if (crontab -l 2>/dev/null | grep -Fq "$DIR/cron.php"); then
    echo "CRON job for $DIR/cron.php already exists. No changes made."
else
    # Add the new CRON job.
    # This command first lists existing crontab entries, then echoes the new CRON_JOB line,
    # and finally pipes both back to 'crontab -' to install them.
    (crontab -l 2>/dev/null; echo "$CRON_JOB") | crontab -
    echo "✅ CRON job set to run daily at 9 AM IST for: $DIR/cron.php"
fi