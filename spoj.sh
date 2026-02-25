

cd /opt/astro_json/zal


zcat astrobin_block_*.json.gz \
  | sed 's/^\[//; s/\]$//' \
  | sed '/^$/d' \
  | sed 's/,$//' \
  > merged.tmp

sed 's/}$/},/' merged.tmp > merged2.tmp
sed '$ s/},/}/' merged2.tmp > merged3.tmp

echo "[" > all.json
cat merged3.tmp >> all.json
echo "]" >> all.json
gzip -9 all.json
