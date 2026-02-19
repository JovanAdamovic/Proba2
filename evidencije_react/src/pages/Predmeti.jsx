import { useEffect, useMemo, useState } from "react";
import http from "../api/http";
import Card from "../components/Card";
import Input from "../components/Input";
import Button from "../components/Button";
import { useAuth } from "../auth/AuthContext";

export default function Predmeti() {
  const { user } = useAuth();
  const isAdmin = user?.uloga === "ADMIN";

  const [items, setItems] = useState([]);
  const [err, setErr] = useState("");
  const [q, setQ] = useState("");
  const [busyId, setBusyId] = useState(null);

  const [profesori, setProfesori] = useState([]);
  const [studenti, setStudenti] = useState([]);
  const [saving, setSaving] = useState(false);
  const [assigning, setAssigning] = useState(false);
  const [editingId, setEditingId] = useState(null);

  const [form, setForm] = useState({
    naziv: "",
    sifra: "",
    godina_studija: "",
    profesor_ids: [],
    student_ids: [],
  });

  const [editForm, setEditForm] = useState({
    profesor_ids: [],
    student_ids: [],
  });

  async function load() {
    setErr("");
    try {
      const endpoint = isAdmin ? "/predmeti" : "/predmeti/moji";
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

  async function loadUsers() {
    if (!isAdmin) return;
    try {
      const [profRes, studRes] = await Promise.all([
        http.get("/users", { params: { role: "PROFESOR" } }),
        http.get("/users", { params: { role: "STUDENT" } }),
      ]);

      setProfesori(profRes.data?.data || profRes.data || []);
      setStudenti(studRes.data?.data || studRes.data || []);
    } catch (e) {
      setErr(e?.response?.data?.message || "Greška pri učitavanju korisnika");
    }
  }

  useEffect(() => {
    loadUsers();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [isAdmin]);

  const filtered = useMemo(() => {
    const s = q.trim().toLowerCase();
    if (!s) return items;

    return items.filter((p) => {
      const hay = `${p.naziv ?? ""} ${p.sifra ?? ""} ${p.godina_studija ?? ""}`.toLowerCase();
      return hay.includes(s);
    });
  }, [items, q]);

  async function obrisiPredmet(id) {
    if (!isAdmin) return;
    setBusyId(id);
    try {
      await http.delete(`/predmeti/${id}`);
      await load();
    } catch (e) {
      alert(e?.response?.data?.message || "Greška pri brisanju predmeta");
    } finally {
      setBusyId(null);
    }
  }

  function handleMultiSelect(event) {
    return Array.from(event.target.selectedOptions, (option) => Number(option.value));
  }

  async function kreirajPredmet(event) {
    event.preventDefault();
    if (!isAdmin) return;

    setSaving(true);
    setErr("");

    try {
      await http.post("/predmeti", {
        naziv: form.naziv,
        sifra: form.sifra,
        godina_studija: Number(form.godina_studija),
        profesor_ids: form.profesor_ids,
        student_ids: form.student_ids,
      });

      setForm({
        naziv: "",
        sifra: "",
        godina_studija: "",
        profesor_ids: [],
        student_ids: [],
      });

      await load();
    } catch (e) {
      setErr(e?.response?.data?.message || "Greška pri čuvanju predmeta");
    } finally {
      setSaving(false);
    }
  }

  function startEdit(predmet) {
    setEditingId(predmet.id);
    setEditForm({
      profesor_ids: (predmet.profesori || []).map((p) => p.id),
      student_ids: (predmet.studenti || []).map((s) => s.id),
    });
  }

  async function sacuvajDodele(predmetId) {
    if (!isAdmin) return;

    setAssigning(true);
    setErr("");

    try {
      await http.put(`/predmeti/${predmetId}`, {
        profesor_ids: editForm.profesor_ids,
        student_ids: editForm.student_ids,
      });

      setEditingId(null);
      await load();
    } catch (e) {
      setErr(e?.response?.data?.message || "Greška pri čuvanju dodela");
    } finally {
      setAssigning(false);
    }
  }

  return (
    <div style={{ padding: 16, display: "grid", gap: 12 }}>
      <h2>{isAdmin ? "Predmeti" : "Moji predmeti"}</h2>

      {isAdmin && (
        <Card>
          <form onSubmit={kreirajPredmet} style={{ display: "grid", gap: 12 }}>
            <h3>Dodaj predmet</h3>

            <Input
              placeholder="Naziv predmeta"
              value={form.naziv}
              onChange={(e) => setForm((prev) => ({ ...prev, naziv: e.target.value }))}
              required
            />

            <Input
              placeholder="Šifra"
              value={form.sifra}
              onChange={(e) => setForm((prev) => ({ ...prev, sifra: e.target.value }))}
              required
            />

            <Input
              placeholder="Godina studija"
              type="number"
              min="1"
              max="8"
              value={form.godina_studija}
              onChange={(e) => setForm((prev) => ({ ...prev, godina_studija: e.target.value }))}
              required
            />

            <label style={{ display: "grid", gap: 6 }}>
              <span>Profesori</span>
              <select
                multiple
                value={form.profesor_ids.map(String)}
                onChange={(e) => setForm((prev) => ({ ...prev, profesor_ids: handleMultiSelect(e) }))}
                style={{ padding: 8, borderRadius: 8, border: "1px solid #ddd", minHeight: 120 }}
              >
                {profesori.map((profesor) => (
                  <option key={profesor.id} value={profesor.id}>
                    {profesor.ime} {profesor.prezime}
                  </option>
                ))}
              </select>
            </label>

            <label style={{ display: "grid", gap: 6 }}>
              <span>Studenti</span>
              <select
                multiple
                value={form.student_ids.map(String)}
                onChange={(e) => setForm((prev) => ({ ...prev, student_ids: handleMultiSelect(e) }))}
                style={{ padding: 8, borderRadius: 8, border: "1px solid #ddd", minHeight: 120 }}
              >
                {studenti.map((student) => (
                  <option key={student.id} value={student.id}>
                    {student.ime} {student.prezime}
                  </option>
                ))}
              </select>
            </label>

            <Button type="submit" disabled={saving}>
              {saving ? "Čuvam..." : "Sačuvaj predmet"}
            </Button>
          </form>
        </Card>
      )}

      <div style={{ maxWidth: 420 }}>
        <Input
          placeholder="Pretraga (naziv/šifra/godina)..."
          value={q}
          onChange={(e) => setQ(e.target.value)}
        />
      </div>

      {err && <div style={{ color: "crimson" }}>{err}</div>}

      {filtered.map((p) => (
        <Card key={p.id}>
          <div style={{ display: "flex", justifyContent: "space-between", gap: 12 }}>
            <div>
              <div>
                <b>{p.naziv}</b> ({p.sifra})
              </div>
              <div>Godina: {p.godina_studija}</div>

              {isAdmin && (
                <>
                  <div>
                    Profesori:{" "}
                    {(p.profesori || [])
                      .map((prof) => `${prof.ime} ${prof.prezime}`)
                      .join(", ") || "—"}
                  </div>
                  <div>Studenti: {(p.studenti || []).length}</div>
                </>
              )}
            </div>

            {isAdmin && (
              <div style={{ display: "grid", alignContent: "start", gap: 8 }}>
                <Button onClick={() => obrisiPredmet(p.id)} disabled={busyId === p.id}>
                  {busyId === p.id ? "Brišem..." : "Obriši"}
                </Button>

                <Button onClick={() => startEdit(p)} disabled={editingId === p.id}>
                  {editingId === p.id ? "Uređuješ..." : "Uredi dodele"}
                </Button>
              </div>
            )}
          </div>

          {isAdmin && editingId === p.id && (
            <div style={{ marginTop: 16, display: "grid", gap: 12 }}>
              <label style={{ display: "grid", gap: 6 }}>
                <span>Profesori</span>
                <select
                  multiple
                  value={editForm.profesor_ids.map(String)}
                  onChange={(e) =>
                    setEditForm((prev) => ({
                      ...prev,
                      profesor_ids: handleMultiSelect(e),
                    }))
                  }
                  style={{ padding: 8, borderRadius: 8, border: "1px solid #ddd", minHeight: 120 }}
                >
                  {profesori.map((profesor) => (
                    <option key={profesor.id} value={profesor.id}>
                      {profesor.ime} {profesor.prezime}
                    </option>
                  ))}
                </select>
              </label>

              <label style={{ display: "grid", gap: 6 }}>
                <span>Studenti</span>
                <select
                  multiple
                  value={editForm.student_ids.map(String)}
                  onChange={(e) =>
                    setEditForm((prev) => ({
                      ...prev,
                      student_ids: handleMultiSelect(e),
                    }))
                  }
                  style={{ padding: 8, borderRadius: 8, border: "1px solid #ddd", minHeight: 120 }}
                >
                  {studenti.map((student) => (
                    <option key={student.id} value={student.id}>
                      {student.ime} {student.prezime}
                    </option>
                  ))}
                </select>
              </label>

              <div style={{ display: "flex", gap: 8 }}>
                <Button onClick={() => sacuvajDodele(p.id)} disabled={assigning}>
                  {assigning ? "Čuvam..." : "Sačuvaj dodele"}
                </Button>
                <Button onClick={() => setEditingId(null)} disabled={assigning}>
                  Otkaži
                </Button>
              </div>
            </div>
          )}
        </Card>
      ))}

      {!err && filtered.length === 0 && <div>Nema podataka.</div>}
    </div>
  );
}