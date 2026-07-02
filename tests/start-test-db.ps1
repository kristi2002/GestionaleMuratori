# Starts (or reuses) the throwaway MySQL 8 container used by tests/run.php.
#   powershell -ExecutionPolicy Bypass -File tests/start-test-db.ps1
# Container: gm-test-mysql on 127.0.0.1:3307, root password "test".

$name = "gm-test-mysql"

$existing = docker ps -a --filter "name=^/$name$" --format "{{.Names}}"
if ($existing -eq $name) {
    docker start $name | Out-Null
    Write-Host "Container '$name' started (reused)."
} else {
    docker run -d --name $name `
        -e MYSQL_ROOT_PASSWORD=test `
        -p 127.0.0.1:3307:3306 `
        mysql:8.0 | Out-Null
    Write-Host "Container '$name' created."
}

Write-Host "Waiting for MySQL to accept connections..."
for ($i = 0; $i -lt 60; $i++) {
    $out = docker exec $name mysqladmin ping -h 127.0.0.1 -ptest 2>$null
    if ("$out" -match "alive") {
        Write-Host "MySQL test DB ready on 127.0.0.1:3307 (root/test)."
        exit 0
    }
    Start-Sleep -Seconds 1
}
Write-Error "MySQL did not become ready in 60s."
exit 1
