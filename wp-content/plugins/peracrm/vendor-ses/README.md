# PeraCRM SES signer distribution

This is an offline, plugin-owned **subset**, not a Composer installation of the
whole AWS SDK. It contains the established AWS SDK for PHP `SignatureV4` signing
implementation from **3.402.0**, its interface/trait and exception dependencies,
plus Guzzle PSR-7 **2.8.0** request/response/stream utilities and PSR HTTP Message
**2.0** interfaces. `dependencies.lock.json` pins each upstream source SHA-256,
packaged SHA-256, repository, version/ref and selected path. Apache-2.0/MIT
licenses and AWS NOTICE are retained under `licenses/`. All PHP sources have
been mechanically modified only to prefix namespaces/references with
`PeraCRM\SESDeps\` and normalize trailing whitespace; the lock records the
modified bytes. No signing algorithm
has been implemented or edited locally. Selected utility APIs are internal only.

The PHP sources and private autoloader ship in the normal plugin directory;
production hosts need no Composer/network/install step. The private autoloader
registers only the prefixed namespace, lazily, when `ses-credentials.php` is
explicitly included. It does not use webpush's autoloader, an ambient Composer
installation, another plugin or a global AWS/Guzzle/PSR namespace. Webpush's
existing dependency versions were inspected; independent copies of the selected
message classes are packaged here. No Guzzle client/middleware, retry stack,
credential provider chain, metadata client, CRT/V4a runtime, JMESPath, SimpleXML
or service endpoint-discovery code is distributed or used. SignatureV4 retains
upstream dormant CRT references, isolated by the same prefix; the transport
always selects ordinary SigV4 and never calls those paths.

Supported transport platform: **PHP >=8.2**, matching the pinned SDK requirement;
core JSON/hash/PCRE and **ext-curl**, with libcurl SSL and asynchronous DNS support
(checked at construction). TLS trust uses the host's configured CA trust; it is
never disabled. Pure SigV4 does not require CRT, SimpleXML or OpenSSL's PHP
extension. Other plugin functionality retains its existing platform requirements.
Local verification used PHP 8.4; hosted CI supplies additional runtime evidence.

Verify shipped bytes with `python3 vendor-ses/package.py --verify` from the plugin
directory. To reproduce, obtain the exact upstream refs recorded in the lock,
place their source roots at `SOURCE_ROOT/aws/aws-sdk-php`,
`SOURCE_ROOT/guzzlehttp/psr7` and `SOURCE_ROOT/psr/http-message`, then run
`python3 vendor-ses/package.py --sources SOURCE_ROOT`. This verifies upstream
hashes before writing and verifies transformed hashes afterward. No network or
resolver is used by the packager. Dependency updates require a reviewed lock,
license/platform review, regeneration and signature/HTTP regressions. Never
modify shipped dependency PHP directly. The complete Guzzle or SDK public API
is not supported by this deliberately selected subset.

The SDK signs the SES v2 REST/JSON request; cURL handles exactly one HTTP
execution. This avoids acquiring the full SDK's credential/discovery/retry
machinery solely to sign one fixed service operation. No production bootstrap,
factory, scheduler, worker, or endpoint constructs this transport. Deploying these
files alone cannot enable campaign dispatch. Mocks do not establish SES account,
IAM, received MIME/DKIM, real-host timeout, or inbox acceptance.
