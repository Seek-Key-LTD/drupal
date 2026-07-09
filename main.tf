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
    null = {
      source = "hashicorp/null"
    }
  }
}

provider "docker" {
  host = "unix:///run/podman/podman.sock"
}

provider "vault" {
  address = "https://vault.git4ta.fun"
  skip_child_token = true
}

data "docker_network" "traefik" {
  name = "traefik"
}

resource "null_resource" "pre_deploy_check" {
  triggers = {
    always_run = timestamp()
  }

  provisioner "local-exec" {
    command = <<PRE_EOF
      # 重点是一条龙：若 settings.php 丢失，直接通过 sudo mc 从 S3 云端备份库中秒级秒拉恢复
      if [ ! -f "/opt/drupal/sites/default/settings.php" ]; then
        sudo mc --config-dir /home/ben/.mc cp backup/terraform-state/drupal/settings.php /opt/drupal/sites/default/settings.php
        sudo chmod 644 /opt/drupal/sites/default/settings.php
        echo "settings.php restored from S3 backup via mc."
      fi
PRE_EOF
  }
}

resource "docker_container" "drupal" {
  depends_on = [
    null_resource.pre_deploy_check,
    data.docker_network.traefik
  ]

  name    = "drupal"
  image   = "localhost/drupal-redis:latest"
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
    "DB_PASS=CZTqVMU9oMercE#",
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
  # 一条龙挂载：挂载 OAuth 2.0 密钥文件夹
  volumes {
    host_path      = "/opt/drupal/keys"
    container_path = "/opt/drupal/keys"
    read_only      = true
  }

  labels {
    label = "traefik.enable"
    value = "true"
  }
  labels {
    label = "traefik.http.routers.drupal.rule"
    value = "Host(\"drupal.seekkey.eu.org\")"
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
