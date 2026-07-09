terraform {
  required_providers {
    docker = {
      source  = "kreuzwerker/docker"
      version = "~> 3.0"
    }
    vault = {
      source  = "hashicorp/vault"
      version = "~> 3.0"
    }
  }
}

provider "docker" {
  host = "unix:///run/podman/podman.sock"
}

provider "vault" {
  address = "https://vault.git4ta.fun"
  auth_login {
    approle {
      role_id   = var.vault_role_id
      secret_id = var.vault_secret_id
    }
  }
}

data "docker_network" "traefik" {
  name = "traefik"
}

data "vault_kv_secret_v2" "drupal" {
  mount = "kv"
  path  = "dev"
}

resource "docker_container" "drupal" {
  name    = "drupal"
  image   = "ghcr.io/seek-key-ltd/drupal-redis:latest"
  restart = "unless-stopped"

  networks_advanced {
    name = data.docker_network.traefik.id
  }

  env = [
    "REDIS_HOST=dragonfly",
    "REDIS_PORT=6379",
    "DB_TYPE=mysql",
    "DB_HOST=10.0.0.100",
    "DB_PORT=3306",
    "DB_USER=admin",
    "DB_PASS=${data.vault_kv_secret_v2.drupal.data.password}",
    "DB_NAME=drupal",
  ]

  volumes {
    host_path      = "/opt/drupal/modules"
    container_path = "/opt/drupal/modules"
    read_only      = true
  }
  volumes {
    host_path      = "/opt/drupal/profiles"
    container_path = "/opt/drupal/profiles"
    read_only      = true
  }
  volumes {
    host_path      = "/opt/drupal/sites"
    container_path = "/opt/drupal/web/sites"
    read_only      = false
  }
  volumes {
    host_path      = "/opt/drupal/themes"
    container_path = "/opt/drupal/themes"
    read_only      = true
  }

  labels {
    label = "traefik.enable"
    value = "true"
  }
  labels {
    label = "traefik.http.routers.drupal.rule"
    value = "Host(drupal.seekkey.eu.org)"
  }
  labels {
    label = "traefik.http.routers.drupal.entrypoints"
    value = "websecure"
  }
  labels {
    label = "traefik.http.routers.drupal.tls.certresolver"
    value = "cloudflare"
  }
}

variable "vault_role_id" {
  type      = string
  sensitive = true
}

variable "vault_secret_id" {
  type      = string
  sensitive = true
}
