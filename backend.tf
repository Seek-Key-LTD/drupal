terraform {
  backend "s3" {
    endpoint                    = "https://fgws3-ocloud.ihep.ac.cn"
    bucket                      = "21579-lhhq-164014"
    key                         = "drupal/terraform.tfstate"
    region                      = "us-east-1"
    skip_credentials_validation = true
    skip_metadata_api_check     = true
    skip_requesting_account_id  = true
    force_path_style            = true
  }
}
