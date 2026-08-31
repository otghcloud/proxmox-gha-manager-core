<img src="https://otgh-static-assets.s3.otgh.cloud/branding/logos/otgh_cloud_2024.png" alt="OTGH Cloud" width="200px" />

# Security Policy

We (**Aurora Technology (OTGH) Ltd**) create these projects for use within our own production environments and offer them freely to give back to the open source community we ourselves rely on.

As a commercial entity, we take the security of any code we release seriously and have processes in place to ensure the continued safety of anything we release to either our customers or the general public.

## Processes

This project utilises several approaches to ensure code safety.

- **Dependabot**: We use GitHub's Dependabot action which regularly checks any third party dependencies for vulnerabilities and version updates.
- **Supply chain security**: Seperately from the above, internally we use several tools to ensure supply chain vulnerabilities are avoided for mitigated where a package manager is used (apt/docker/composer/npm/pip etc).

Every pull request to this repository is reviewed (and if accepted,  merged) by a member of our team to ensure malicious code does not silently end up in one of our releases.

## Reporting a Vulnerability

If you've found a vulnerability within this repository, please **DO NOT** immediately raise a public GitHub issue.

Instead, we ask that you report any vulnerabilities privately by emailing [open-source@otgh.cloud](mailto:open-source@otgh.cloud) with the following details:

- Affected version(s)
- Clear reproduction steps (if applicable)
- Severity rating
- Source (in repo or third party dependency)
- Vulnerable files (if applicable)

Upon emailing your report, you'll receive an automated case reference and a member of our team will review your report within 24 hours.
