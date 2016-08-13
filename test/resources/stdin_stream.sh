#!/bin/sh -e

if [ "$#" != "1" ]; then
    echo "Usage: ./stdin_stream.sh <stdout|stderr>" >&2
    exit 2
fi

if [ "$1" = "stdout" ] || [ "$1" = "STDOUT" ]; then
    cat -
else
    cat - >&2
fi
