# php-dns-server

A small DNS server written in plain PHP 8.4 - no framework, no runtime
dependencies beyond three PHP extensions. It serves a BIND-style zone file over
UDP and, for names outside that zone, resolves them recursively from the root
servers, caching the results in memory.

This is a learning/hobby project. It implements enough of RFC 1035 and RFC 6891
to answer real `dig` queries, but it is **not** production software: no TCP
listener, no zone transfers, no DNSSEC, and no protection against amplification
abuse. Don't expose it to the public internet.

## Requirements

- PHP >= 8.4
- Extensions: `mbstring`, `sockets`, `ctype`
- Composer (for the autoloader and dev tooling)

## Installation

```bash
git clone git@github.com:reynevan/php-dns-server.git
cd php-dns-server
composer install
```

## Running

```bash
php index.php -p 5353 -a 127.0.0.1 -z example.zone
```

```
Listening on '127.0.0.1:5353'
```

Then query it with `dig`:

```console
$ dig @127.0.0.1 -p 5353 www.example.com A

;; ->>HEADER<<- opcode: QUERY, status: NOERROR, id: 20536
;; flags: qr aa rd ra; QUERY: 1, ANSWER: 1, AUTHORITY: 0, ADDITIONAL: 1

;; QUESTION SECTION:
;www.example.com.               IN      A

;; ANSWER SECTION:
www.example.com.        3600    IN      A       192.0.2.10
```

```console
$ dig @127.0.0.1 -p 5353 example.com MX +short
10 mail.example.com.
20 mail2.example.com.

$ dig @127.0.0.1 -p 5353 example.com SOA +short
ns1.example.com. admin.example.com. 2020080302 7200 3600 1209600 3600
```

Names the zone does not cover are resolved recursively:

```console
$ dig @127.0.0.1 -p 5353 iana.org A +short
192.0.43.8
```

The server runs in the foreground; stop it with `Ctrl+C`.

### Options

| Short | Long                 | Default        | Description                          |
|-------|----------------------|----------------|--------------------------------------|
| `-p`  | `--port`             | `5353`         | UDP port to listen on                |
| `-a`  | `--address`          | `0.0.0.0`      | Bind address                         |
| `-z`  | `--zone`             | `example.zone` | Zone file, relative to the repo root |
| `-i`  | `--identity-address` | `127.0.0.1`    | Address the server answers PTR for   |
| `-n`  | `--identity-name`    | `dns.rnvn`     | Name returned for that PTR           |
| `-r`  | `--recursive`        | off            | Advertise recursion availability     |
| `-h`  | `--help`             | -              | Print usage and exit                 |

Notes:

- **Ports below 1024 need privileges.** To serve on the standard port 53, run
  with `sudo`, or grant the binary the capability once:
  `sudo setcap cap_net_bind_service=+ep $(which php)`.
- `-z` takes an absolute path as-is; anything else is resolved against the
  repository root.

## Zone file format

The parser accepts the common subset of the BIND master-file syntax. See
[`example.zone`](example.zone) for a commented file covering every supported
case.

```zone
$ORIGIN example.com.
$TTL 3600

@       3600    IN  SOA     ns1.example.com. admin.example.com. (
        2020080302  ; Serial
        7200        ; Refresh
        3600        ; Retry
        1209600     ; Expire
        3600 )      ; Negative TTL

www.example.com.    A       192.0.2.10   ; absolute name, defaults for TTL/class
mail                IN  A   192.0.2.20   ; relative name, resolved against $ORIGIN
ftp     7200        A       192.0.2.30   ; explicit TTL
db      IN  1800    A       192.0.2.40   ; class and TTL in either order
        IN          A       192.0.2.41   ; blank name inherits the previous one
@       IN  MX      10 mail.example.com.
```

Supported:

- `$ORIGIN` and `$TTL` directives, including changing them mid-file
- `@` for the zone apex, relative names expanded with `$ORIGIN`, trailing-dot
  absolute names
- A blank leading field inherits the name from the previous record
- Fields separated by spaces or tabs
- TTL and class each optional, in either order
- `;` comments, including trailing ones, with `;` inside quotes left alone
- Multi-line records wrapped in parentheses

### Record type support
- A
- AAAA
- CAA
- CNAME
- MX
- NS
- PTR
- SOA
- TXT

## Development

```bash
composer install

vendor/bin/phpunit          # unit tests
vendor/bin/phpstan analyse  # static analysis, level 6, src/ only
vendor/bin/phpcs            # PSR-12, src/ and tests/
vendor/bin/phpcbf           # auto-fix what phpcs can
```

All three run on every push and pull request via
[`.github/workflows/ci.yml`](.github/workflows/ci.yml) on PHP 8.4.


## License
MIT
