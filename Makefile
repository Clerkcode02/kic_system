.PHONY: up down logs fresh psql redis-cli

up:
	docker compose up -d

down:
	docker compose down

logs:
	docker compose logs -f

fresh:
	docker compose down -v
	docker compose up -d

psql:
	docker compose exec postgres psql -U $${DB_USERNAME:-marketplace} -d $${DB_DATABASE:-marketplace}

redis-cli:
	docker compose exec redis redis-cli
