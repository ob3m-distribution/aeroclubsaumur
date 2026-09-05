import os, sys, paramiko
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from deploy import HOST, PORT, USER, PWD, REMOTE

SRC = r"C:\Users\AOBOFF~1\AppData\Local\Temp\claude\C--Users-AOB-Office-Documents-OB3M-Distribution---Claude\ee7d2bec-66f8-49e8-bc33-cfce9204446f\scratchpad\pdf_out"
BASE = REMOTE + "/docs-adherents"
HT = "# Acces direct interdit : les PDF passent par doc-adherent.php (connexion requise)\n<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\n"

def mkdirs(sftp, path):
    cur=""
    for m in path.strip("/").split("/"):
        cur+="/"+m
        try: sftp.stat(cur)
        except FileNotFoundError: sftp.mkdir(cur)

t=paramiko.Transport((HOST,PORT)); t.connect(username=USER,password=PWD)
sftp=paramiko.SFTPClient.from_transport(t)
mkdirs(sftp, BASE)
# .htaccess deny
import io
with sftp.open(BASE+"/.htaccess","w") as fh: fh.write(HT)
n=0; total=0
for root,_,files in os.walk(SRC):
    for f in sorted(files):
        if not f.lower().endswith(".pdf"): continue
        loc=os.path.join(root,f)
        rel=os.path.relpath(loc,SRC).replace("\\","/")
        dst=BASE+"/"+rel
        mkdirs(sftp, dst.rsplit("/",1)[0])
        sftp.put(loc,dst)
        n+=1; total+=os.path.getsize(loc)
        print(f"[{n}/83] {rel}  ({os.path.getsize(loc)//1024} Ko)", flush=True)
print(f"\nUPLOAD OK : {n} PDF, {total/1048576:.0f} Mo -> {BASE}")
t.close()
