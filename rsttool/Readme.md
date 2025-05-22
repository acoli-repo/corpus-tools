# RSTTool by Michael O'Donnell, Wagsoft Linguistic Software

The RSTTool is a classic, but dated annotation tool for creating annotations according to Rhetorical Structure Theory (RST).

## TL/DR

Under Linux, run 

	bash -e rsttool.sh

or

	./rsttool.sh

Optionally, this can be followed by a file to be loaded, but this seems to only support only `*.rs3`  and (RST-)XML files. Default is (RST-)XML.

> As of May 2025, the Linux edition of RSTTool 3.42 is not runnable under Ubuntu 22.04L because its depends on TCL/TK 8.3.
> Instead, `rsttool.sh` uses the [Windows version 3.45](./www.wagsoft.com/RSTTool/RSTTool345Install.exe) that can be unzipped and executed with `wine` (wine-6.0.3, Ubuntu 6.0.3~repack-1).

## Content

- [`www.wagsoft.com`](./www.wagsoft.com) Full mirror of the RSTTool website from 2025-05-22 in case the original hosting falters. Includes binaries.
- [`www.sfu.ca`](./www.sfu.ca) Full mirror of the RST website from 2025-05-22. Development stalled in January 2024.

## Notes

- https://www.sfu.ca/rst/: The home of RST, development stalled in January 2024
- https://wiki.gucorpling.org/gum/rst: Some alternative tooling, incl. https://gucorpling.org/rstweb/info/ (server-side annotation tool)