import { useEffect, useMemo, useState } from "react";
import http from "../api/http";
import Card from "../components/Card";
import Input from "../components/Input";
import { useAuth } from "../auth/AuthContext";
import Button from "../components/Button";
import Modal from "../components/Modal";

function formatRok(value) {
  if (!value) return "-";

   if (typeof value === "string") {
    const isoLike = value.match(/^(\d{4})-(\d{2})-(\d{2})[T\s](\d{2}):(\d{2})/);
    if (isoLike) {
      const [, y, m, d, hh, mm] = isoLike;
      return `${d}.${m}.${y}. ${hh}:${mm}`;
    }

    const onlyDate = value.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (onlyDate) {
      const [, y, m, d] = onlyDate;
      return `${d}.${m}.${y}.`;
    }
  }

  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return String(value);

  return d.toLocaleString("sr-RS", {
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
    hour: "2-digit",
    minute: "2-digit",
  });
}

export default function Zadaci() {
  const { user } = useAuth();
  const isAdmin = user?.uloga === "ADMIN";
  const isProfesor = user?.uloga === "PROFESOR";

  const [items, setItems] = useState([]);
  const [q, setQ] = useState("");
  const [err, setErr] = useState("");
  const [busyId, setBusyId] = useState(null);

  const [createErr, setCreateErr] = useState("");
  const [createBusy, setCreateBusy] = useState(false);
  const [predmeti, setPredmeti] = useState([]);
  const [form, setForm] = useState({
    predmetId: "",
    naslov: "",
    opis: "",
    rokPredaje: "",
  });

  const [open, setOpen] = useState(false);
  const [selected, setSelected] = useState(null);

  async function load() {
    setErr("");
    try {
      const endpoint = isAdmin ? "/zadaci" : "/zadaci/moji";
      const res = await http.get(endpoint);
      setItems(res.data.data || res.data || []);
    } catch (e) {
      setErr(e?.response?.data?.message || "Greška pri učitavanju");
    }
  }

  useEffect(() => {
    load();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [isAdmin]);

  useEffect(() => {
    if (!isProfesor) return;

    async function loadPredmeti() {
      setCreateErr("");
      try {
        const res = await http.get("/predmeti/moji");
        setPredmeti(res.data.data || res.data || []);
      } catch (e) {
        setCreateErr(e?.response?.data?.message || "Greška pri učitavanju predmeta");
        setPredmeti([]);
      }
    }

    loadPredmeti();
  }, [isProfesor]);

  const filtered = useMemo(() => {
    const s = q.trim().toLowerCase();
    if (!s) return items;

    return items.filter((z) => {
      const hay = `${z.naslov ?? ""} ${z.opis ?? ""} ${z.rok_predaje ?? ""}`.toLowerCase();
      return hay.includes(s);
    });
  }, [items, q]);

  function openDetails(z) {
    setSelected(z);
    setOpen(true);
  }

  async function obrisiZadatak(id) {
    if (!isAdmin) return;
    setBusyId(id);
    try {
      await http.delete(`/zadaci/${id}`);
      await load();
    } catch (e) {
      alert(e?.response?.data?.message || "Greška pri brisanju zadatka");
    } finally {
      setBusyId(null);
    }
  }

  async function kreirajZadatak(e) {
    e.preventDefault();
    if (!isProfesor) return;

    setCreateErr("");

    if (!form.predmetId || !form.naslov || !form.rokPredaje) {
      setCreateErr("Popuni predmet, naslov i rok.");
      return;
    }

    setCreateBusy(true);
    try {
      await http.post("/zadaci", {
        predmet_id: form.predmetId,
        naslov: form.naslov,
        opis: form.opis || null,
        rok_predaje: form.rokPredaje,
      });

      setForm({ predmetId: "", naslov: "", opis: "", rokPredaje: "" });
      await load();
    } catch (e2) {
      const msg =
        e2?.response?.data?.message ||
        (e2?.response?.data?.errors
          ? Object.values(e2.response.data.errors).flat().join(" ")
          : null) ||
        "Greška pri kreiranju zadatka";
      setCreateErr(msg);
    } finally {
      setCreateBusy(false);
    }
  }

  return (
    <div style={{ padding: 16, display: "grid", gap: 12 }}>
      <h2>{isAdmin ? "Zadaci" : "Moji zadaci"}</h2>

      {isProfesor && (
        <Card>
          <div style={{ fontWeight: 800, marginBottom: 10 }}>Novi zadatak</div>

          <form onSubmit={kreirajZadatak} style={{ display: "grid", gap: 10, maxWidth: 520 }}>
            <div style={{ display: "grid", gap: 6 }}>
              <label>Predmet</label>
              <select
                value={form.predmetId}
                onChange={(e) => setForm((prev) => ({ ...prev, predmetId: e.target.value }))}
                style={{
                  width: "100%",
                  padding: "10px 12px",
                  borderRadius: 10,
                  border: "1px solid #ddd",
                  outline: "none",
                }}
                required
              >
                <option value="">-- Izaberi predmet --</option>
                {predmeti.map((p) => (
                  <option key={p.id} value={p.id}>
                    {p.naziv}
                  </option>
                ))}
              </select>
            </div>

            <div style={{ display: "grid", gap: 6 }}>
              <label>Naslov</label>
              <Input
                placeholder="Naslov zadatka"
                value={form.naslov}
                onChange={(e) => setForm((prev) => ({ ...prev, naslov: e.target.value }))}
                required
              />
            </div>

            <div style={{ display: "grid", gap: 6 }}>
              <label>Opis</label>
              <Input
                placeholder="Opis"
                value={form.opis}
                onChange={(e) => setForm((prev) => ({ ...prev, opis: e.target.value }))}
              />
            </div>

            <div style={{ display: "grid", gap: 6 }}>
               <label>Rok predaje (datum i vreme)</label>
              <Input
                type="datetime-local"
                value={form.rokPredaje}
                onChange={(e) => setForm((prev) => ({ ...prev, rokPredaje: e.target.value }))}
                required
              />
            </div>

            {createErr && <div style={{ color: "crimson" }}>{createErr}</div>}

            <div style={{ display: "flex", gap: 8 }}>
              <Button type="submit" disabled={createBusy}>
                {createBusy ? "Kreiram..." : "Kreiraj zadatak"}
              </Button>
            </div>
          </form>
        </Card>
      )}

      <div style={{ maxWidth: 420 }}>
        <Input
          placeholder="Pretraga (naslov/opis/rok)..."
          value={q}
          onChange={(e) => setQ(e.target.value)}
        />
      </div>

      {err && <div style={{ color: "crimson" }}>{err}</div>}

      {filtered.map((z) => (
        <Card key={z.id}>
          <div style={{ display: "flex", justifyContent: "space-between", gap: 12 }}>
            <div>
              <div>
                <b>{z.naslov}</b>
              </div>
              <div>Rok: {formatRok(z.rok_predaje)}</div>
              <div style={{ fontSize: 13, color: "#555" }}>{z.opis}</div>
            </div>

            <div style={{ display: "grid", gap: 8, alignContent: "start" }}>
              <Button onClick={() => openDetails(z)}>Detalji</Button>

              {isAdmin && (
                <Button onClick={() => obrisiZadatak(z.id)} disabled={busyId === z.id}>
                  {busyId === z.id ? "Brišem..." : "Obriši"}
                </Button>
              )}
            </div>
          </div>
        </Card>
      ))}

      {!err && filtered.length === 0 && <div>Nema podataka.</div>}

      <Modal
        open={open}
        title={selected ? `Zadatak #${selected.id}` : "Zadatak"}
        onClose={() => setOpen(false)}
      >
        {selected && (
          <div style={{ display: "grid", gap: 8 }}>
            <div>
              <b>Naslov:</b> {selected.naslov}
            </div>
            <div>
              <b>Rok:</b> {formatRok(selected.rok_predaje)}
            </div>
            <div>
              <b>Opis:</b> {selected.opis ?? "-"}
            </div>
          </div>
        )}
      </Modal>
    </div>
  );
}