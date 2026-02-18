# Symfony Proxy Service

A Symfony-based proxy that intercepts requests to target websites (php.net, symfony.com, etc.) and appends `™` to every six-letter word. 

---

## Prerequisites

- Docker & Docker Compose
- kubectl (for Kubernetes)
- Kind or Minikube (for local Kubernetes cluster)

---

## Quick Start (Docker Compose)

### 1. Configure Target Website

Edit `.env`:

```bash
# Default target
PROXY_DEFAULT_TARGET=https://www.php.net

# Alternative target
PROXY_DEFAULT_TARGET=https://symfony.com
```

### 2. Start the Application

```bash
# First time or after code changes
docker compose up -d --build

# After changing .env only
docker compose restart php
```

### 3. Access the Proxy

Open [http://localhost:8000](http://localhost:8000)

### 4. Stop the Application

```bash
docker compose down
```

---

## Switching Targets

1. Edit `.env` to the new target.
2. Restart the PHP container:

```bash
docker compose restart php
```

3. Refresh browser at [http://localhost:8000](http://localhost:8000)

---

## Running Tests

```bash
docker compose up -d
docker compose exec php bin/phpunit
```

---

## Kubernetes Deployment

### Setup Cluster & Load Image

```bash
# Create Kind cluster
kind create cluster --name symfony-cluster

# Build image
docker build -t username/symfony-proxy:latest .

# Load into Kind
kind load docker-image username/symfony-proxy:latest --name symfony-cluster
```

### Deploy

**Dev (1 replica, debug on, default target php.net):**

```bash
kubectl apply -k k8s/overlays/dev
kubectl wait --for=condition=ready pod -l env=dev --timeout=60s
kubectl port-forward svc/symfony-proxy-service-dev 8080:80
```

**Prod (3 replicas, debug off):**

```bash
kubectl apply -k k8s/overlays/prod
kubectl port-forward svc/symfony-proxy-service-prod 8080:80
```

### Change Target in Kubernetes

Edit `deployment_patch.yaml` for your environment, apply, then restart:

```bash
kubectl apply -k k8s/overlays/dev
kubectl rollout restart deployment symfony-proxy-dev
```

---

## Configuration

Set via `.env` (Docker Compose) or manifests (Kubernetes):

- **APP_ENV** – Symfony environment (dev/prod)
- **APP_SECRET** – Symfony secret key
- **PROXY_TARGETS** – Comma-separated allowed sites
- **PROXY_DEFAULT_TARGET** – Default target
