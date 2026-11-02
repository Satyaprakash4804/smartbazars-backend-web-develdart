@echo off
REM SmartBazars - start the backend so phones on the same Wi-Fi can reach it.
REM Change the IP below if your PC's Wi-Fi IP changes (run "ipconfig" to see it).
set LAN_IP=10.156.105.54
cd /d "%~dp0"
echo.
echo  SmartBazars backend starting...
echo  Admin panel  : http://%LAN_IP%:8000/admin
echo  Seller panel : http://%LAN_IP%:8000/seller
echo  Partner panel: http://%LAN_IP%:8000/partner
echo  App API test : http://%LAN_IP%:8000/api/categories
echo  (Allow port 8000 in Windows Firewall if asked. Keep this window open.)
echo.
php artisan optimize:clear
php artisan serve --host=0.0.0.0 --port=8000
pause
