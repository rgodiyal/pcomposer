#!/bin/sh

echo "Make phar file..."
php build-phar.php

echo "Making it executable..."
chmod +x build/pcomposer.phar

echo "Moving to /usr/local/bin/"
sudo cp build/pcomposer.phar /usr/local/bin/pcomposer

echo "Installation complete! Run 'pcomposer' to use."
