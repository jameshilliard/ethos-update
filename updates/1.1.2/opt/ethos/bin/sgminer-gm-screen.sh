#!/bin/bash
trap ctrl_c INT

function ctrl_c() {
        echo "PRESS CTRL+A+D to get back to prompt."
}

/opt/miners/sgminer-gm/sgminer-gm "$@"
