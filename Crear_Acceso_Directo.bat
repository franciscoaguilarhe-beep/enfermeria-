@echo off
set "SCRIPT=%TEMP%\crear_acceso_%RANDOM%.vbs"
echo Set oWS = WScript.CreateObject("WScript.Shell") >> "%SCRIPT%"
echo sLinkFile = oWS.SpecialFolders("Desktop") ^& "\Sistema Enfermeria SRAE.lnk" >> "%SCRIPT%"
echo Set oLink = oWS.CreateShortcut(sLinkFile) >> "%SCRIPT%"
echo oLink.TargetPath = "C:\Program Files\Google\Chrome\Application\chrome.exe" >> "%SCRIPT%"
echo oLink.Arguments = "--app=http://localhost/enfermeria/public/index.php" >> "%SCRIPT%"
echo oLink.IconLocation = "C:\xampp\htdocs\enfermeria\public\favicon.ico" >> "%SCRIPT%"
echo oLink.Save >> "%SCRIPT%"
cscript /nologo "%SCRIPT%"
del "%SCRIPT%"
echo Acceso directo creado en el escritorio con exito.
pause
