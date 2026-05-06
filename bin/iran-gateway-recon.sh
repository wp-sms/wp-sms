#!/usr/bin/env bash
# iran-gateway-recon.sh
#
# Run this from a machine with direct (non-proxied) Iran-network access.
# It curls each gateway site, saves raw responses, and generates a Markdown
# report with extracted titles + likely API/docs/webservice links so we can
# decide which providers are worth implementing.
#
# Output goes to ./iran-gateway-recon-output/ next to this script.
# A tar.gz of the output dir is produced at the end — paste/attach the
# report or the archive in chat afterwards.

set -u
set -o pipefail
# NOTE: deliberately not `set -e` — one bad host should not abort the run.

SCRIPT_DIR="$( cd -- "$( dirname -- "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"
OUT_DIR="${SCRIPT_DIR}/iran-gateway-recon-output"
HTML_DIR="${OUT_DIR}/html"
HEAD_DIR="${OUT_DIR}/headers"
LINKS_DIR="${OUT_DIR}/links"
REPORT="${OUT_DIR}/REPORT.md"
SUMMARY_CSV="${OUT_DIR}/summary.csv"
LOG="${OUT_DIR}/run.log"

UA='Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36'
MAX_TIME=25
CONNECT_TIME=10

mkdir -p "$HTML_DIR" "$HEAD_DIR" "$LINKS_DIR"
: > "$LOG"

###############################################################################
# Gateway list — slug<TAB>website<TAB>implemented<TAB>docs_url(optional)
# Source: Notion Gateways DB (countries=Iran), fetched 2026-05-06.
# Edit freely if the Notion list changes.
###############################################################################
GATEWAYS=$(cat <<'TSV'
0098sms	https://www.0098sms.com/	yes
18sms	http://18sms.ir/	yes
adpdigital	http://adpdigital.com	yes
adspanel	http://adspanel.ir/	yes
afe	http://afe.ir	yes
aradsms	http://arad-sms.ir/	yes
arkapayamak	http://www.arkapayamak.ir/	yes
asanak	http://asanak.ir/	yes
avalpayam	http://avalpayam.com/	yes
bandarsms	http://bandarsms.ir/	yes
bestit	https://www.bestit.co/	yes
candoosms	http://candoosms.com/	yes
chapargah	http://chapargah.ir/	yes
farapayamak	https://farapayamak.ir/	yes	https://github.com/Farapayamak
farazsms	https://farazsms.com/	yes	https://docs2.farazsms.com/
firstpayamak	http://firstpayamak.ir/	yes
ghasedak	https://ghasedaksms.com/	yes
hirosms	https://www.hiro-sms.com/	yes
hostiran	http://hostiran.net	yes
idehpayam	https://www.idehpayam.com/	yes
imencms	http://imencms.ir	yes
iransmspanel	http://iransmspanel.ir	yes
iranspk	http://iranspk.ir/	yes
ismsie	http://isms.ir/	yes
jahanpayamak	http://www.jahanpayamak.info/	yes
kavenegar	https://kavenegar.com/	yes
loginpanel	http://loginpanel.ir/	yes
markazpayamak	https://www.markazpayamak.ir/	yes
matinsms	https://matinsms.ir	yes
mdpanel	http://ippanel.com/	yes
mediana	http://mediana.ir/	yes
melipayamak	https://www.melipayamak.com/	yes
mydnspanel	http://mydnspanel.com/	yes
nasrpayam	http://nasrpayam.ir	yes
novin1sms	http://www.novin1sms.ir	yes
onlinepanel	http://onlinepanel.ir/	yes
paaz	https://paaz.ir/	yes
parsasms	http://www.parsasms.com/	yes
parsgreen	http://www.parsgreen.com/	yes
payamakaria	http://www.payamakaria.ir/	yes
payamakpanel	http://payamak-panel.com/	yes
payameroz	http://payameroz.ir	yes
payamresan	https://payam-resan.com/	yes	https://doc.sms-webservice.com
persiansms	http://www.persian-sms.com/	yes
rayansmspanel	http://rayansmspanel.ir/	yes
raygansms	https://raygansms.com/	yes
razpayamak	http://razpayamak.com/	yes
sabanovin	http://sabanovin.com/	yes
signalads	https://signalads.com	yes
sms-ir	https://sms.ir	yes	https://sms.ir/rest-api/
smsban	http://smsban.ir/	yes
smsbartar	http://sms-bartar.com	yes
smscall	http://smscall.ir	yes
smsclick	https://smsclick.ir/	yes
smshooshmand	http://smshooshmand.com/	yes
smsline	http://www.smsline.ir/	yes
smsmelli	http://smsmelli.com/	yes
smsservice	http://smsservice.ir/	yes
smstoos	http://smstoos.ir/	yes
ssmss	http://www.ssmss.ir/	yes
sunwaysms	http://sunwaysms.com	yes
textsms	http://www.textsms.ir	yes
tsms	http://tsms.ir	yes
TSV
)

###############################################################################
# Helpers
###############################################################################

log() { printf '%s %s\n' "$(date +%H:%M:%S)" "$*" | tee -a "$LOG" >&2; }

# Extract <title>…</title> from an HTML file. Falls back to og:title if missing.
extract_title() {
    local f="$1"
    local t
    t=$(tr '\n' ' ' < "$f" \
        | grep -oiE '<title[^>]*>[^<]{1,300}</title>' \
        | head -n1 \
        | sed -E 's#<title[^>]*>##i; s#</title>##i; s/^[[:space:]]+//; s/[[:space:]]+$//')
    if [[ -z "$t" ]]; then
        t=$(grep -oiE 'property=["'\'']og:title["'\''][^>]*content=["'\''][^"'\'']{1,300}["'\'']' "$f" \
            | head -n1 \
            | sed -E 's/.*content=["'\'']([^"'\'']*)["'\''].*/\1/')
    fi
    printf '%s' "$t"
}

# Pull candidate "docs / api / webservice / panel" links from HTML.
extract_doc_links() {
    local f="$1"
    # Get every href, dedupe, then keep only the ones that look documentation-y.
    grep -oiE 'href=["'\''][^"'\'' >]+["'\'']' "$f" \
        | sed -E 's/^href=["'\'']//; s/["'\'']$//' \
        | awk '!seen[$0]++' \
        | grep -iE '(doc|docs|documentation|api|rest|webservice|web-service|sdk|swagger|developer|راهنما|پنل|مستندات|وب-?سرويس|وب-?سرویس)' \
        || true
}

# Persist Persian-friendly text by stripping tags + scripts/styles.
to_text() {
    local f="$1"
    sed -E ':a;N;$!ba;s#<script[^>]*>.*?</script>##gI; s#<style[^>]*>.*?</style>##gI' "$f" \
        | sed -E 's/<[^>]+>/ /g' \
        | tr -s '[:space:]' ' '
}

slugify_url() {
    # Reduce a URL to a usable filename suffix; not used for slug since we already have one.
    printf '%s' "$1" | sed -E 's#^https?://##; s#[/?#&=].*$##'
}

# fetch_one URL HTML_FILE HEADER_FILE -> prints "status<TAB>finalurl<TAB>size_bytes"
fetch_one() {
    local url="$1" body="$2" hdr="$3"
    local status final_url size

    # -k tolerates self-signed / expired certs (very common for IR providers).
    # --compressed handles gzip; --location follows redirects.
    if ! curl -sS -L -k --compressed \
            -A "$UA" \
            --connect-timeout "$CONNECT_TIME" \
            --max-time "$MAX_TIME" \
            -o "$body" \
            -D "$hdr" \
            -w '%{http_code}\t%{url_effective}\t%{size_download}\n' \
            "$url" 2>>"$LOG"; then
        printf '000\t%s\t0\n' "$url"
        return 1
    fi
}

###############################################################################
# Main loop
###############################################################################

log "Starting Iran gateway recon. Output → $OUT_DIR"
echo 'slug,implemented,notion_docs_url,attempted_url,http_status,final_url,bytes,title,doc_links_count' > "$SUMMARY_CSV"

# Report header
cat > "$REPORT" <<EOF
# Iran SMS Gateway Recon Report

Generated: $(date -u +'%Y-%m-%dT%H:%M:%SZ')
Run from: $(hostname) ($(uname -srm))
Total gateways: $(printf '%s\n' "$GATEWAYS" | grep -c .)

Each entry below shows what the site returned when curled directly. Look for:
- HTTP status (200 = page served)
- Page title (often Persian; gives a hint at what the site is)
- Candidate doc / API / webservice / panel links extracted from the homepage
- Whether a wp-sms provider is already implemented for that slug

EOF

while IFS=$'\t' read -r slug website implemented notion_docs; do
    [[ -z "$slug" ]] && continue
    log "→ $slug ($website)"

    body="${HTML_DIR}/${slug}.html"
    hdr="${HEAD_DIR}/${slug}.headers"
    links="${LINKS_DIR}/${slug}.links.txt"

    : > "$body"; : > "$hdr"; : > "$links"

    # Try the URL as given.
    result=$(fetch_one "$website" "$body" "$hdr" || true)
    status=$(printf '%s' "$result" | cut -f1)
    final=$(printf '%s' "$result" | cut -f2)
    size=$(printf '%s' "$result" | cut -f3)

    # If https failed, retry with http (and vice versa).
    if [[ "$status" == "000" || "$status" == "" ]]; then
        if [[ "$website" == https://* ]]; then
            alt="http://${website#https://}"
        elif [[ "$website" == http://* ]]; then
            alt="https://${website#http://}"
        else
            alt=""
        fi
        if [[ -n "$alt" ]]; then
            log "  retry → $alt"
            result=$(fetch_one "$alt" "$body" "$hdr" || true)
            status=$(printf '%s' "$result" | cut -f1)
            final=$(printf '%s' "$result" | cut -f2)
            size=$(printf '%s' "$result" | cut -f3)
        fi
    fi

    title="(no title)"
    doc_links_count=0
    if [[ -s "$body" ]]; then
        t=$(extract_title "$body")
        [[ -n "$t" ]] && title="$t"
        extract_doc_links "$body" > "$links" || true
        doc_links_count=$(wc -l < "$links" | tr -d ' ')
    fi

    # CSV line — quote fields that may contain commas/quotes.
    csv_title=$(printf '%s' "$title" | sed 's/"/""/g')
    csv_final=$(printf '%s' "$final" | sed 's/"/""/g')
    printf '%s,%s,"%s",%s,%s,"%s",%s,"%s",%s\n' \
        "$slug" "$implemented" "$notion_docs" "$website" "$status" "$csv_final" "$size" "$csv_title" "$doc_links_count" \
        >> "$SUMMARY_CSV"

    # Markdown section
    {
        echo
        echo "## ${slug}"
        echo
        echo "- Notion website: \`${website}\`"
        echo "- Notion docs_url: \`${notion_docs:-(none)}\`"
        echo "- wp-sms provider already implemented: **${implemented}**"
        echo "- HTTP status: **${status}**"
        echo "- Final URL: \`${final}\`"
        echo "- Bytes: ${size}"
        echo "- Title: \`${title}\`"
        if [[ "$doc_links_count" -gt 0 ]]; then
            echo
            echo "Candidate doc/API links (${doc_links_count}):"
            echo
            echo '```'
            head -n 40 "$links"
            echo '```'
        else
            echo "- No doc/API links matched on homepage."
        fi
    } >> "$REPORT"

done <<< "$GATEWAYS"

###############################################################################
# Wrap up
###############################################################################

log "Building tarball..."
tar -czf "${OUT_DIR}.tar.gz" -C "$(dirname "$OUT_DIR")" "$(basename "$OUT_DIR")" 2>>"$LOG" || true

log "Done."
echo
echo "Summary:"
echo "  Report:   $REPORT"
echo "  CSV:      $SUMMARY_CSV"
echo "  HTML dir: $HTML_DIR"
echo "  Tarball:  ${OUT_DIR}.tar.gz"
echo
echo "Reachability table:"
awk -F, 'NR>1 {print $5"\t"$1}' "$SUMMARY_CSV" | sort | uniq -c -f0 -w3 | head -20 || true
echo
echo "Top of report:"
head -n 12 "$REPORT"
