let
	nixpkgs = fetchTarball "https://github.com/NixOS/nixpkgs/tarball/nixos-25.11";
	pkgs = import nixpkgs { config = {}; overlays = []; };
in

pkgs.mkShellNoCC {
	packages = with pkgs; [
    nodePackages_latest.bower
    nodejs_24
    php
    php84Packages.composer
];
}
