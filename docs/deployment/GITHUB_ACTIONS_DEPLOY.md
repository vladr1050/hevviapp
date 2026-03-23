# Деплой через GitHub Actions

Репозиторий: **https://github.com/vladr1050/hevviapp** (SSH: `git@github.com:vladr1050/hevviapp.git`).

Workflow: **`.github/workflows/deploy-production.yml`**

Запускается при **push в ветку `master`** и вручную: **Actions → Deploy production → Run workflow**.

---

## Что делает workflow

1. Клонирует репозиторий на runner.
2. **rsync** на сервер (как локальный скрипт): без `.env`, `.git`, `node_modules`.
3. По **SSH** на сервере: `composer install`, `npm install` + `npm run build`, миграции, `cache:clear`, `restart php nginx`, `cache:warmup`.

`.env` на сервере **не перезаписывается**.

---

## Секреты репозитория (Settings → Secrets and variables → Actions)

| Secret | Пример | Описание |
|--------|--------|----------|
| `DEPLOY_SSH_KEY` | содержимое **приватного** ключа | Ключ должен быть добавлен в `~/.ssh/authorized_keys` пользователя на сервере (рекомендуется отдельный ключ только для деплоя). |
| `DEPLOY_HOST` | `37.27.188.238` | IP или hostname сервера. |
| `DEPLOY_USER` | `root` | SSH-пользователь. |
| `DEPLOY_PATH` | `/var/www/frpc_hevii-php-backoffice-service` | Каталог проекта на сервере (как у `deploy-production.sh`). |

### Создать ключ только для GitHub

**На своём Mac:**

```bash
ssh-keygen -t ed25519 -C "github-actions-hevvi-deploy" -f ~/.ssh/hevvi_github_actions -N ""
```

**Публичный** ключ добавить на сервер:

```bash
ssh-copy-id -i ~/.ssh/hevvi_github_actions.pub root@37.27.188.238
# или вручную: cat hevvi_github_actions.pub >> ~/.ssh/authorized_keys
```

**Приватный** ключ (`~/.ssh/hevvi_github_actions` — целиком, включая `BEGIN`/`END`) вставить в GitHub → **DEPLOY_SSH_KEY**.

---

## Проверка

После первого успешного запуска:

- зелёный job в **Actions**;
- сайт https://hevvi.app открывается;
- при смене фронта — обновление с **Cmd+Shift+R**.

---

## Ограничения

- Runner GitHub должен достучаться до сервера по **SSH (порт 22)**. Если доступ только с белого списка IP — нужен [self-hosted runner](https://docs.github.com/en/actions/hosting-your-own-runners) или другой способ деплоя.
- Ветка деплоя зашита как **`master`**. Если переименуешь на `main`, поправь `branches` в workflow.

---

## Отключить автодеплой

Удали или переименуй файл workflow, либо убери блок `push:` и оставь только `workflow_dispatch` для ручного запуска.
