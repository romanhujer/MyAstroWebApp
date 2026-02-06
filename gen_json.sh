#!/bin/bash

# pevná cesta k pyenv uživatele hujer
export PYENV_ROOT="/home/hujer/.pyenv"
export PATH="$PYENV_ROOT/bin:$PATH"

# inicializace pyenv (nutné pro cron)
eval "$($PYENV_ROOT/bin/pyenv init -)"

# aktivace prostředí
pyenv activate sky310

# log time
date

# přejít do pracovního adresáře
# cd /opt/astro_json || exit 1


./komety_eph.py

#./moon_faze.py

#./moon_eph.py
#./sun_eph.py
#./planet_eph.py

