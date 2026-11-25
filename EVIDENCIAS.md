Comprobando el estado:

```bash
docker compose ps
```
<img width="1126" height="200" alt="Captura desde 2026-11-24 19-16-38" src="https://github.com/user-attachments/assets/f03db7c8-1354-41a1-ad72-6bd5abad1dca" />

Comprobando servicios: 

```bash
http://localhost:8000/api/documentation
http://localhost:3000
```
<img width="1271" height="736" alt="Captura desde 2026-11-24 19-20-23" src="https://github.com/user-attachments/assets/07c66d5b-b015-420f-9d26-87fb17161c28" />
<img width="1271" height="736" alt="image" src="https://github.com/user-attachments/assets/68319c80-82a5-40e7-890d-455784d632dc" />

Pruebas "Smoke":

```bash
./scripts/smoke-test.sh
```
<img width="622" height="254" alt="Captura desde 2026-11-24 19-25-53" src="https://github.com/user-attachments/assets/68079b06-d633-4db9-9311-1fc9a5030799" />

Persistencia de datos:

```bash
docker compose down
docker compose up -d
```
<img width="620" height="245" alt="image" src="https://github.com/user-attachments/assets/d836ea92-74c4-4ddc-a160-cd4daae52d56" />
<img width="1262" height="715" alt="image" src="https://github.com/user-attachments/assets/d936ba27-1223-4517-abfc-e6acc403fde6" />

