# Déploiement — Oracle Cloud "Always Free" + Tailscale

Cadence tourne dans un seul conteneur (FrankenPHP + SQLite), exposé **en privé**
via Tailscale (pas d'ouverture de port public, donc pas besoin d'authentification).

## 1. Créer la VM (gratuite à vie)

1. Compte sur https://www.oracle.com/cloud/free/ (CB demandée pour vérification, non débitée).
2. **Create Instance** :
   - Image : **Ubuntu 24.04 (aarch64)**
   - Shape : **VM.Standard.A1.Flex** (Ampere ARM) — reste dans l'Always Free : 1 OCPU / 6 Go suffisent largement.
   - Ajoute ta clé SSH publique.
3. Pas besoin d'ouvrir de port entrant : on passe par Tailscale.

## 2. Préparer le serveur (en SSH)

```bash
ssh ubuntu@<IP_PUBLIQUE_DE_LA_VM>

# Docker
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker $USER && exec sg docker newgrp   # (ou reconnecte-toi)

# Tailscale
curl -fsSL https://tailscale.com/install.sh | sudo sh
sudo tailscale up        # ouvre l'URL affichée pour connecter la VM à ton tailnet
```

## 3. Récupérer le code + configurer

```bash
git clone https://github.com/Thomas-DE-SOUSA/cadence.git
cd cadence

cp .env.production.example .env.production
# Génère une APP_KEY et colle-la dans .env.production :
docker run --rm dunglas/frankenphp:1-php8.4 sh -c \
  'php -r "echo \"base64:\".base64_encode(random_bytes(32)).\"\n\";"'
# -> édite .env.production : APP_KEY=base64:....  et APP_URL=https://<host>.<tailnet>.ts.net
# (optionnel) ANTHROPIC_API_KEY=... pour activer le coach IA
nano .env.production
```

## 4. Lancer

```bash
docker compose -f docker-compose.prod.yml up -d --build
docker compose -f docker-compose.prod.yml logs -f   # vérifie "migrated" + FrankenPHP démarré
```

L'app écoute sur `127.0.0.1:8000` (privé à la VM).

## 5. Exposer en HTTPS privé (Tailscale)

```bash
sudo tailscale serve --bg 8000
sudo tailscale serve status   # affiche l'URL https://<host>.<tailnet>.ts.net
```

Ouvre cette URL sur ton téléphone (Tailscale connecté) → **Ajouter à l'écran d'accueil**
pour installer la PWA. Accessible partout, en privé.

> Renseigne cette même URL dans `APP_URL` (.env.production) puis
> `docker compose -f docker-compose.prod.yml up -d` pour la prendre en compte.

## 6. Mettre à jour l'app

```bash
cd cadence && git pull
docker compose -f docker-compose.prod.yml up -d --build   # rebuild + migrations auto
```

## 7. Sauvegardes SQLite (recommandé)

La base vit dans le volume `cadence-data` (`/data/database.sqlite`). Sauvegarde quotidienne :

```bash
# crontab -e
0 3 * * * docker compose -f /home/ubuntu/cadence/docker-compose.prod.yml exec -T app \
  sh -c 'sqlite3 /data/database.sqlite ".backup /data/backup-$(date +\%F).sqlite"' \
  && find /var/lib/docker/volumes -name 'backup-*.sqlite' -mtime +14 -delete
```

(Idéalement, copie aussi ces backups hors de la VM.)

## Notes
- Tout est **privé via Tailscale** → pas d'exposition publique, pas besoin de login pour l'instant.
  Si un jour tu veux une URL publique, ajoute une authentification d'abord.
- Le conteneur redémarre tout seul (`restart: unless-stopped`) au reboot de la VM.
