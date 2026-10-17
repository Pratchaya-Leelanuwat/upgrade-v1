param([string]$BaseUrl = 'http://127.0.0.1:8080')
$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.Net.Http

function Assert-Equal($Expected, $Actual, [string]$Name) {
    if ($Expected -ne $Actual) { throw "$Name failed: expected $Expected, got $Actual" }
    Write-Host "PASS $Name"
}
function New-Client {
    $handler = [System.Net.Http.HttpClientHandler]::new()
    $handler.AllowAutoRedirect = $false
    $handler.CookieContainer = [System.Net.CookieContainer]::new()
    return [System.Net.Http.HttpClient]::new($handler)
}

$client = New-Client
try {
    $root = $client.GetAsync("$BaseUrl/").GetAwaiter().GetResult()
    Assert-Equal 200 ([int]$root.StatusCode) 'home page'
    $homeBody = $root.Content.ReadAsStringAsync().GetAwaiter().GetResult()
    if ($homeBody -notmatch '/style\.css\?v=20261008-ui5') { throw 'Versioned stylesheet reference missing; stale browser CSS may be reused' }
    Write-Host 'PASS versioned stylesheet cache bust'
    if ($homeBody -notmatch 'name="q"' -or $homeBody -notmatch 'href="/\?sort=popular"' -or $homeBody -notmatch 'href="/\?sort=unanswered"') { throw 'Home page search, popular sort, or unanswered filter missing' }
    Write-Host 'PASS home search and sort controls rendered'
    $search = $client.GetAsync("$BaseUrl/?q=smoke+test").GetAwaiter().GetResult()
    Assert-Equal 200 ([int]$search.StatusCode) 'home search request'
    if ($search.Content.ReadAsStringAsync().GetAwaiter().GetResult() -notmatch 'ผลค้นหา: smoke test') { throw 'Search term was not applied or shown' }
    Write-Host 'PASS home search query'
    $popular = $client.GetAsync("$BaseUrl/?sort=popular").GetAwaiter().GetResult()
    Assert-Equal 200 ([int]$popular.StatusCode) 'popular sort request'
    $unanswered = $client.GetAsync("$BaseUrl/?sort=unanswered").GetAwaiter().GetResult()
    Assert-Equal 200 ([int]$unanswered.StatusCode) 'unanswered filter request'
    $login = $client.GetAsync("$BaseUrl/login").GetAwaiter().GetResult()
    Assert-Equal 200 ([int]$login.StatusCode) 'login page'
    $loginBody = $login.Content.ReadAsStringAsync().GetAwaiter().GetResult()
    if ($loginBody -notmatch '<label>อีเมล<input' -or $loginBody -match 'อีเมล หรือชื่อผู้ใช้เดิม') { throw 'Login email label is incorrect' }
    Write-Host 'PASS login label is email only'
    [System.Collections.Generic.IEnumerable[string]]$loginCookies = $null
    [void]$login.Headers.TryGetValues('Set-Cookie', [ref]$loginCookies)
    if (($loginCookies -join ';') -notmatch '(?i)(?:^|;\s*)Secure(?:;|$)') { throw 'APP_HTTPS session cookie missing Secure' }
    if (-not $login.Headers.Contains('Strict-Transport-Security')) { throw 'APP_HTTPS response missing HSTS' }
    $register = $client.GetAsync("$BaseUrl/register").GetAwaiter().GetResult()
    Assert-Equal 200 ([int]$register.StatusCode) 'registration page'

    $profile = $client.GetAsync("$BaseUrl/profile").GetAwaiter().GetResult()
    Assert-Equal 303 ([int]$profile.StatusCode) 'profile requires login'
    Assert-Equal '/login' ($profile.Headers.Location.ToString()) 'profile redirects to login'
    $newPost = $client.GetAsync("$BaseUrl/new").GetAwaiter().GetResult()
    Assert-Equal 303 ([int]$newPost.StatusCode) 'posting requires login'
    $badImage = $client.GetAsync("$BaseUrl/image?name=..%2F..%2Fetc%2Fpasswd").GetAwaiter().GetResult()
    Assert-Equal 404 ([int]$badImage.StatusCode) 'image path traversal rejected'
    $threadInjection = $client.GetAsync("$BaseUrl/thread?id=2%20OR%201%3D1").GetAwaiter().GetResult()
    if ([int]$threadInjection.StatusCode -notin @(200, 404)) { throw "thread id injection returned unexpected status $([int]$threadInjection.StatusCode)" }
    $threadBody = $threadInjection.Content.ReadAsStringAsync().GetAwaiter().GetResult()
    if ($threadBody -match '(?i)SQLSTATE|PDOException|SQL syntax|Fatal error|Stack trace') { throw 'thread route exposed a database error' }
    Write-Host 'PASS thread id SQL injection probe and generic error response'
    $getLogout = $client.GetAsync("$BaseUrl/logout").GetAwaiter().GetResult()
    Assert-Equal 405 ([int]$getLogout.StatusCode) 'logout rejects GET'

    $empty = [System.Collections.Generic.Dictionary[string,string]]::new()
    $post = [System.Net.Http.FormUrlEncodedContent]::new($empty)
    $csrf = $client.PostAsync("$BaseUrl/login", $post).GetAwaiter().GetResult()
    Assert-Equal 400 ([int]$csrf.StatusCode) 'login POST requires CSRF token'

    $secureClient = New-Client
    try {
        $request = [System.Net.Http.HttpRequestMessage]::new([System.Net.Http.HttpMethod]::Get, "$BaseUrl/login")
        [void]$request.Headers.TryAddWithoutValidation('CF-Connecting-IP', '198.51.100.20')
        [void]$request.Headers.TryAddWithoutValidation('CF-Visitor', '{"scheme":"https"}')
        $secure = $secureClient.SendAsync($request).GetAwaiter().GetResult()
        [System.Collections.Generic.IEnumerable[string]]$setCookie = $null
        [void]$secure.Headers.TryGetValues('Set-Cookie', [ref]$setCookie)
        $cookieHeader = $setCookie -join ';'
        if ($cookieHeader -notmatch '(?i)(?:^|;\s*)Secure(?:;|$)') { throw 'HTTPS session cookie missing Secure' }
        if ($cookieHeader -notmatch '(?i)HttpOnly' -or $cookieHeader -notmatch '(?i)SameSite=Strict') { throw 'Session cookie missing HttpOnly or SameSite=Strict' }
        if (-not $secure.Headers.Contains('Strict-Transport-Security')) { throw 'HTTPS response missing HSTS' }
        Write-Host 'PASS trusted Cloudflare HTTPS session protections'
        $secure.Dispose(); $request.Dispose()
    } finally { $secureClient.Dispose() }

    $server = $login.Headers.Server.ToString()
    if ($server -match '\d') { throw "Apache version banner still exposed: $server" }
    Write-Host 'PASS Apache version banner hidden'
    foreach ($header in @('X-Content-Type-Options', 'X-Frame-Options', 'Referrer-Policy', 'Permissions-Policy')) {
        if (-not $login.Headers.Contains($header)) { throw "Missing security header: $header" }
    }
    Write-Host 'PASS common Apache security headers'
    $static = $client.GetAsync("$BaseUrl/style.css").GetAwaiter().GetResult()
    $cacheControl = if ($static.Headers.Contains('Cache-Control')) { $static.Headers.GetValues('Cache-Control') -join ',' } else { '' }
    if ($cacheControl -notmatch 'max-age=86400') { throw 'Static asset cache policy missing or incorrect' }
    Write-Host 'PASS static asset cache policy'

    $times = @()
    1..5 | ForEach-Object {
        $watch = [Diagnostics.Stopwatch]::StartNew()
        $response = $client.GetAsync("$BaseUrl/").GetAwaiter().GetResult()
        $watch.Stop(); $times += $watch.ElapsedMilliseconds; $response.Dispose()
    }
    Write-Host "INFO home GET ms (5 sequential requests): $($times -join ', ')"
} finally { $client.Dispose() }
