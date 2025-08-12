@echo off
REM PComposer v1.0.0 Installer for Windows

echo Installing PComposer v1.0.0...

REM Detect installation directory
set INSTALL_DIR=%USERPROFILE%\AppData\Local\pcomposer
if not exist "%INSTALL_DIR%" mkdir "%INSTALL_DIR%"

REM Copy executable
copy "pcomposer-1.0.0.php" "%INSTALL_DIR%\pcomposer.php"

REM Create batch file
echo @echo off > "%INSTALL_DIR%\pcomposer.bat"
echo php "%%~dp0pcomposer.php" %%* >> "%INSTALL_DIR%\pcomposer.bat"

REM Add to PATH
setx PATH "%PATH%;%INSTALL_DIR%"

echo PComposer installed successfully!
echo Location: %INSTALL_DIR%\pcomposer.bat
echo Usage: pcomposer --help
pause
