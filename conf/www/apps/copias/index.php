<?php
session_set_cookie_params(['domain' => '.insrv5.local']);
session_start();

$backupDir = '/backups';

// -----------------------------------------------------------------------------
// 1. API AJAX (Comprobación en tiempo real del semáforo)
// -----------------------------------------------------------------------------
if (isset($_GET['check_status'])) {
	header('Content-Type: application/json');
	$isBusy = file_exists("$backupDir/.lanzar_copia") ||
		file_exists("$backupDir/.restaurar_db") ||
		file_exists("$backupDir/.restaurar_ldap") ||
		file_exists("$backupDir/.restaurar_tar") ||
		file_exists("$backupDir/.eliminar_archivo");

	$resultado = null;
	$resultFile = "$backupDir/.resultado";
	if (file_exists($resultFile)) {
		$resultado = trim(file_get_contents($resultFile));
		unlink($resultFile); // leer y destruir
	}

	echo json_encode(['busy' => $isBusy, 'resultado' => $resultado]);
	exit;
}
// -----------------------------------------------------------------------------
// 2. PREVISUALIZACIÓN DE ARCHIVOS
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['preview'])) {
	$archivo = basename($_GET['preview']);
	$ruta = "$backupDir/$archivo";

	if (file_exists($ruta)) {
		if (strpos($archivo, '.tar.gz') !== false) {
			die("<div style='font-family:sans-serif; padding:2rem; color:#333;'><h3>Previsualización no disponible</h3><p>Los archivos binarios comprimidos no se pueden visualizar en texto plano.</p></div>");
		}
		$contenido = file_get_contents($ruta, false, null, 0, 2500000);
		echo "<!DOCTYPE html><html lang='es'><head><title>Preview: $archivo</title><script src='https://cdn.tailwindcss.com'></script></head>";
		echo "<body class='bg-slate-900 p-6'><div class='max-w-5xl mx-auto'>";
		echo "<div class='flex justify-between items-center mb-4'><h2 class='text-emerald-400 font-mono text-sm'>Vista previa de: $archivo</h2>";
		echo "<button onclick='window.close()' class='bg-slate-700 hover:bg-slate-600 text-white text-xs px-3 py-1 rounded'>Cerrar</button></div>";
		echo "<pre class='bg-black p-4 rounded-lg text-emerald-500 font-mono text-xs overflow-x-auto whitespace-pre-wrap border border-slate-700'>" . htmlspecialchars($contenido) . "\n\n... [Contenido truncado] ...</pre>";
		echo "</div></body></html>";
		exit;
	}
}

// -----------------------------------------------------------------------------
// 3. RECUPERACIÓN DE SESIÓN (PRG)
// -----------------------------------------------------------------------------
$mensaje = $_SESSION['mensaje'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['mensaje'], $_SESSION['error']);

// -----------------------------------------------------------------------------
// 4. LÓGICA CRUD
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_archivo'])) {
	$archivo = basename($_POST['eliminar_archivo']);
	file_put_contents("$backupDir/.eliminar_archivo", escapeshellcmd($archivo));
	header("Location: /");
	exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_copia'])) {
	$servicios = $_POST['servicios'] ?? [];
	if (!empty($servicios)) {
		file_put_contents("$backupDir/.lanzar_copia", escapeshellcmd(implode(',', $servicios)));
	} else {
		$_SESSION['error'] = "Selecciona al menos un servicio.";
	}
	header("Location: /");
	exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restaurar_archivo'])) {
	$archivo = basename($_POST['restaurar_archivo']);
	if (strpos($archivo, '.sql') !== false) file_put_contents("$backupDir/.restaurar_db", escapeshellcmd($archivo));
	elseif (strpos($archivo, '.ldif') !== false) file_put_contents("$backupDir/.restaurar_ldap", escapeshellcmd($archivo));
	elseif (strpos($archivo, '.tar.gz') !== false) file_put_contents("$backupDir/.restaurar_tar", escapeshellcmd($archivo));
	header("Location: /");
	exit;
}

// -----------------------------------------------------------------------------
// 5. LECTURA DE ARCHIVOS
// -----------------------------------------------------------------------------
$archivos = file_exists($backupDir) ? array_diff(scandir($backupDir), array('..', '.')) : [];
$backups = [];

foreach ($archivos as $archivo) {
	if (strpos($archivo, '.sql') !== false || strpos($archivo, '.tar.gz') !== false || strpos($archivo, '.ldif') !== false) {
		$tipo = 'Desconocido';
		$badgeClass = 'bg-slate-100 text-slate-800';
		if (strpos($archivo, 'mysql') !== false) {
			$tipo = 'Base de Datos';
			$badgeClass = 'bg-blue-100 text-blue-800 border-blue-200';
		} elseif (strpos($archivo, 'ldap') !== false) {
			$tipo = 'Directorio LDAP';
			$badgeClass = 'bg-purple-100 text-purple-800 border-purple-200';
		} elseif (strpos($archivo, 'grafana') !== false) {
			$tipo = 'Grafana (Data)';
			$badgeClass = 'bg-orange-100 text-orange-800 border-orange-200';
		} elseif (strpos($archivo, 'vpn') !== false) {
			$tipo = 'VPN (Config)';
			$badgeClass = 'bg-red-100 text-red-800 border-red-200';
		} elseif (strpos($archivo, 'redmine') !== false) {
			$tipo = 'Redmine (Files)';
			$badgeClass = 'bg-rose-100 text-rose-800 border-rose-200';
		} elseif (strpos($archivo, 'mail') !== false) {
			$tipo = 'Mail (Buzones)';
			$badgeClass = 'bg-amber-100 text-amber-800 border-amber-200';
		} elseif (strpos($archivo, 'backup_stack') !== false) {
			$tipo = 'Stack Completo';
			$badgeClass = 'bg-emerald-100 text-emerald-800 border-emerald-200';
		}

		$backups[] = [
			'nombre' => $archivo,
			'tamano' => round(filesize("$backupDir/$archivo") / 1024 / 1024, 2) . ' MB',
			'fecha' => date("d/m/Y H:i:s", filemtime("$backupDir/$archivo")),
			'tipo' => $tipo,
			'badgeClass' => $badgeClass
		];
	}
}
usort($backups, function ($a, $b) use ($backupDir) {
	return filemtime("$backupDir/{$b['nombre']}") - filemtime("$backupDir/{$a['nombre']}");
});
?>

<!DOCTYPE html>
<html lang="es">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Gestor de Backups y Regresión</title>
	<script src="https://cdn.tailwindcss.com"></script>
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
	<style>
		body {
			font-family: 'Inter', sans-serif;
		}

		/* Modal backdrop */
		#confirmModal {
			transition: opacity 0.2s ease;
		}

		#confirmModal.hidden {
			opacity: 0;
			pointer-events: none;
		}

		#confirmModal:not(.hidden) {
			opacity: 1;
		}

		/* Modal card entrance */
		#confirmModalCard {
			transition: transform 0.2s ease, opacity 0.2s ease;
		}

		#confirmModal.hidden #confirmModalCard {
			transform: scale(0.95) translateY(8px);
			opacity: 0;
		}

		#confirmModal:not(.hidden) #confirmModalCard {
			transform: scale(1) translateY(0);
			opacity: 1;
		}
	</style>
</head>

<body class="bg-slate-50 min-h-screen text-slate-800">

	<!-- ════════════════════════════════════════════════════
	     MODAL DE CONFIRMACIÓN PERSONALIZADO
	     ════════════════════════════════════════════════════ -->
	<div id="confirmModal" class="hidden fixed inset-0 z-[200] flex items-center justify-center p-4" style="background: rgba(15,23,42,0.6); backdrop-filter: blur(4px);">
		<div id="confirmModalCard" class="bg-white rounded-2xl shadow-2xl w-full max-w-md border border-slate-200 overflow-hidden">

			<!-- Cabecera con icono dinámico -->
			<div id="modalHeader" class="px-6 pt-6 pb-4 flex items-start gap-4">
				<div id="modalIconWrap" class="flex-shrink-0 w-11 h-11 rounded-xl flex items-center justify-center">
					<!-- El icono se inyecta por JS -->
				</div>
				<div>
					<h3 id="modalTitle" class="text-base font-semibold text-slate-900 leading-tight"></h3>
					<p id="modalSubtitle" class="text-sm text-slate-500 mt-1 leading-snug"></p>
				</div>
			</div>

			<!-- Nombre del archivo destacado -->
			<div id="modalFileBox" class="mx-6 mb-5 px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl hidden">
				<span id="modalFileName" class="font-mono text-sm text-slate-700 break-all"></span>
			</div>

			<!-- Botones -->
			<div class="flex gap-3 px-6 pb-6">
				<button id="modalCancelBtn"
					onclick="closeModal()"
					class="flex-1 py-2.5 rounded-xl border border-slate-200 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition">
					Cancelar
				</button>
				<button id="modalConfirmBtn"
					class="flex-1 py-2.5 rounded-xl text-sm font-semibold text-white transition">
					<!-- Texto dinámico -->
				</button>
			</div>
		</div>
	</div>

	<!-- ════════════════════════════════════════════════════
	     OVERLAY DE CARGA
	     ════════════════════════════════════════════════════ -->
	<div id="loadingOverlay" class="fixed inset-0 bg-slate-900/80 backdrop-blur-sm z-[100] hidden flex-col justify-center items-center">
		<div class="animate-spin rounded-full h-20 w-20 border-4 border-indigo-500 border-t-transparent mb-6 shadow-lg"></div>
		<h2 class="text-white text-2xl font-bold tracking-wide">Procesando Operación...</h2>
		<p class="text-indigo-200 mt-2 text-sm font-medium">Docker está trabajando en segundo plano. La página se actualizará sola.</p>
	</div>

	<nav class="bg-white border-b border-slate-200 sticky top-0 z-50 shadow-sm">
		<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
			<div class="flex justify-between items-center h-16">
				<div class="flex items-center space-x-3">
					<div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center text-white font-bold text-xl">I5</div>
					<span class="text-xl font-bold tracking-tight text-slate-900">Insrv5 Workspace</span>
				</div>
				<div class="flex items-center space-x-6">
					<div class="hidden md:flex items-center text-xs font-semibold text-emerald-700 bg-emerald-50 px-3 py-1.5 rounded-full border border-emerald-200">
						<span class="w-2 h-2 rounded-full bg-emerald-500 mr-2 animate-pulse"></span>
						Red Interna / VPN
					</div>
					<div class="flex items-center space-x-3 bg-slate-50 py-1.5 px-3 rounded-full border border-slate-100 hidden sm:flex">
						<div class="w-8 h-8 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center font-bold text-sm">
							<?php echo strtoupper(substr($_SESSION['user_cn'] ?? 'U', 0, 1)); ?>
						</div>
						<div class="flex flex-col">
							<span class="text-sm font-semibold leading-none text-slate-700"><?php echo htmlspecialchars($_SESSION['user_cn'] ?? 'Usuario'); ?></span>
							<span class="text-xs text-slate-500 mt-1"><?php echo htmlspecialchars(strtolower($_SESSION['uid'] ?? $_SESSION['user'] ?? '')); ?>@insrv5.net</span>
						</div>
						<?php
						$rol = $_SESSION['rol'] ?? '';
						$colores_roles = [
							'IT'               => 'bg-indigo-100 text-indigo-700 border-indigo-200',
							'Recursos Humanos' => 'bg-rose-100 text-rose-700 border-rose-200',
							'RRHH'             => 'bg-rose-100 text-rose-700 border-rose-200',
							'Administracion'   => 'bg-violet-100 text-violet-700 border-violet-200',
							'Marketing'        => 'bg-amber-100 text-amber-700 border-amber-200',
						];
						$badge = $colores_roles[$rol] ?? 'bg-slate-100 text-slate-700 border-slate-200';
						?>
						<span class="ml-2 px-2.5 py-0.5 rounded-full text-xs font-bold border <?php echo $badge; ?>">
							<?php echo htmlspecialchars($rol); ?>
						</span>
					</div>
					<a href="https://insrv5.net/users/logout.php" class="flex items-center text-slate-500 hover:text-red-600 transition-colors font-medium text-sm group">
						<span class="hidden sm:block mr-2 group-hover:underline">Salir</span>
						<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
						</svg>
					</a>
				</div>
			</div>
		</div>
	</nav>

	<main class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
		<a href="https://insrv5.net/users/index.php" class="inline-flex items-center text-sm font-medium text-slate-500 hover:text-violet-600 mb-6 transition-colors group">
			<svg class="mr-2 h-4 w-4 transform group-hover:-translate-x-1 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
				<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
			</svg>
			Volver al Dashboard
		</a>
		<h1 class="text-3xl font-bold mb-2 text-slate-900">Centro de Recuperación</h1>
		<p class="text-slate-500 mb-8">Gestión granular de instantáneas y restauración automática.</p>

		<?php if ($mensaje): ?>
			<div class="bg-emerald-50 text-emerald-700 p-4 rounded-xl mb-6 border border-emerald-200 shadow-sm font-medium"><?php echo $mensaje; ?></div>
		<?php endif; ?>
		<?php if ($error): ?>
			<div class="bg-rose-50 text-rose-700 p-4 rounded-xl mb-6 border border-rose-200 shadow-sm font-medium"><?php echo $error; ?></div>
		<?php endif; ?>

		<div class="bg-white rounded-2xl shadow-sm p-7 mb-8 border border-slate-200">
			<h2 class="text-xl font-semibold text-slate-900 mb-5">Nueva Instantánea Manual</h2>
			<form method="POST" data-no-modal>
				<div class="flex flex-wrap gap-3 mb-6">
					<label class="cursor-pointer"><input type="checkbox" name="servicios[]" value="mysql" class="peer sr-only">
						<div class="px-4 py-2 rounded-xl text-sm font-semibold border border-blue-200 text-blue-700 bg-blue-50 peer-checked:bg-blue-600 peer-checked:text-white transition">Base de Datos (MySQL)</div>
					</label>
					<label class="cursor-pointer"><input type="checkbox" name="servicios[]" value="ldap" class="peer sr-only">
						<div class="px-4 py-2 rounded-xl text-sm font-semibold border border-purple-200 text-purple-700 bg-purple-50 peer-checked:bg-purple-600 peer-checked:text-white transition">Directorio (LDAP)</div>
					</label>
					<label class="cursor-pointer"><input type="checkbox" name="servicios[]" value="grafana" class="peer sr-only">
						<div class="px-4 py-2 rounded-xl text-sm font-semibold border border-orange-200 text-orange-700 bg-orange-50 peer-checked:bg-orange-500 peer-checked:text-white transition">Dashboards (Grafana)</div>
					</label>
					<label class="cursor-pointer"><input type="checkbox" name="servicios[]" value="redmine" class="peer sr-only">
						<div class="px-4 py-2 rounded-xl text-sm font-semibold border border-rose-200 text-rose-700 bg-rose-50 peer-checked:bg-rose-600 peer-checked:text-white transition">Adjuntos (Redmine)</div>
					</label>
					<label class="cursor-pointer"><input type="checkbox" name="servicios[]" value="vpn" class="peer sr-only">
						<div class="px-4 py-2 rounded-xl text-sm font-semibold border border-red-200 text-red-700 bg-red-50 peer-checked:bg-red-600 peer-checked:text-white transition">Perfiles VPN</div>
					</label>
					<label class="cursor-pointer"><input type="checkbox" name="servicios[]" value="mail" class="peer sr-only">
						<div class="px-4 py-2 rounded-xl text-sm font-semibold border border-amber-200 text-amber-700 bg-amber-50 peer-checked:bg-amber-500 peer-checked:text-white transition">Buzones (Mail)</div>
					</label>
				</div>
				<div class="flex justify-end border-t border-slate-100 pt-5">
					<button type="submit" name="crear_copia" class="bg-slate-900 hover:bg-slate-800 text-white font-semibold py-2.5 px-6 rounded-xl shadow-md transition">Generar Respaldo</button>
				</div>
			</form>
		</div>

		<h2 class="text-xl font-semibold mb-4 text-slate-900">Repositorio de Volcados</h2>
		<div class="bg-white rounded-2xl overflow-x-auto border border-slate-200 shadow-sm">
			<table class="w-full text-left border-collapse min-w-max">
				<thead>
					<tr class="bg-slate-50 text-slate-500 text-xs tracking-wider uppercase border-b border-slate-200">
						<th class="p-4 font-semibold">Contenido</th>
						<th class="p-4 font-semibold">Archivo</th>
						<th class="p-4 font-semibold">Fecha</th>
						<th class="p-4 font-semibold">Tamaño</th>
						<th class="p-4 font-semibold text-right">Acciones</th>
					</tr>
				</thead>
				<tbody class="divide-y divide-slate-100">
					<?php if (empty($backups)): ?>
						<tr>
							<td colspan="5" class="p-8 text-center text-slate-500">No hay volcados disponibles.</td>
						</tr>
					<?php else: ?>
						<?php foreach ($backups as $b): ?>
							<tr class="hover:bg-slate-50 transition-colors">
								<td class="p-4"><span class="px-3 py-1 rounded-lg text-xs font-semibold border <?php echo $b['badgeClass']; ?>"><?php echo $b['tipo']; ?></span></td>
								<td class="p-4 font-mono text-sm text-slate-600"><?php echo $b['nombre']; ?></td>
								<td class="p-4 text-sm text-slate-500"><?php echo $b['fecha']; ?></td>
								<td class="p-4 text-sm text-slate-500"><?php echo $b['tamano']; ?></td>
								<td class="p-4">
									<div class="flex items-center justify-end gap-2">
										<a href="?preview=<?php echo urlencode($b['nombre']); ?>" target="_blank" title="Previsualizar"
											class="flex items-center justify-center w-9 h-9 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg transition shadow-sm">
											<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
												<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
												<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
											</svg>
										</a>

										<?php if (strpos($b['nombre'], 'backup_stack') === false): ?>
											<form method="POST" class="m-0"
												data-modal="restore"
												data-filename="<?php echo htmlspecialchars($b['nombre']); ?>">
												<input type="hidden" name="restaurar_archivo" value="<?php echo $b['nombre']; ?>">
												<button type="submit" title="Restaurar"
													class="flex items-center justify-center w-9 h-9 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg shadow-sm transition">
													<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
														<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
													</svg>
												</button>
											</form>
										<?php endif; ?>

										<form method="POST" class="m-0"
											data-modal="delete"
											data-filename="<?php echo htmlspecialchars($b['nombre']); ?>">
											<input type="hidden" name="eliminar_archivo" value="<?php echo $b['nombre']; ?>">
											<button type="submit" title="Eliminar"
												class="flex items-center justify-center w-9 h-9 bg-rose-50 hover:bg-rose-100 text-rose-600 rounded-lg shadow-sm transition border border-rose-100">
												<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
													<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
												</svg>
											</button>
										</form>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</main>

	<script>
		const flashMsg = sessionStorage.getItem('flash_mensaje');
		if (flashMsg) {
			sessionStorage.removeItem('flash_mensaje');
			const div = document.createElement('div');
			div.className = 'bg-emerald-50 text-emerald-700 p-4 rounded-xl mb-6 border border-emerald-200 shadow-sm font-medium';
			div.textContent = flashMsg;
			document.querySelector('main').prepend(div);
		}

		// ── Modal de confirmación ──────────────────────────────────────────────
		let pendingForm = null;

		const MODAL_CONFIGS = {
			restore: {
				iconBg: 'bg-indigo-100',
				iconColor: 'text-indigo-600',
				icon: `<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
					<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
				</svg>`,
				title: 'Restaurar copia de seguridad',
				subtitle: 'Se sobrescribirá el estado actual del servicio con los datos de este volcado. Esta acción no se puede deshacer.',
				confirmText: 'Sí, restaurar',
				confirmClass: 'bg-indigo-600 hover:bg-indigo-700',
			},
			delete: {
				iconBg: 'bg-rose-100',
				iconColor: 'text-rose-600',
				icon: `<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
					<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
				</svg>`,
				title: 'Eliminar volcado',
				subtitle: 'El archivo se borrará permanentemente del servidor y no podrá recuperarse.',
				confirmText: 'Sí, eliminar',
				confirmClass: 'bg-rose-600 hover:bg-rose-700',
			}
		};

		function openModal(type, form, filename) {
			const cfg = MODAL_CONFIGS[type];
			pendingForm = form;

			// Rellenar contenido dinámico
			const iconWrap = document.getElementById('modalIconWrap');
			iconWrap.className = `flex-shrink-0 w-11 h-11 rounded-xl flex items-center justify-center ${cfg.iconBg} ${cfg.iconColor}`;
			iconWrap.innerHTML = cfg.icon;

			document.getElementById('modalTitle').textContent = cfg.title;
			document.getElementById('modalSubtitle').textContent = cfg.subtitle;

			const fileBox = document.getElementById('modalFileBox');
			if (filename) {
				document.getElementById('modalFileName').textContent = filename;
				fileBox.classList.remove('hidden');
			} else {
				fileBox.classList.add('hidden');
			}

			const confirmBtn = document.getElementById('modalConfirmBtn');
			confirmBtn.textContent = cfg.confirmText;
			confirmBtn.className = `flex-1 py-2.5 rounded-xl text-sm font-semibold text-white transition ${cfg.confirmClass}`;
			confirmBtn.onclick = submitModal;

			// Mostrar modal
			const modal = document.getElementById('confirmModal');
			modal.classList.remove('hidden');
		}

		function closeModal() {
			document.getElementById('confirmModal').classList.add('hidden');
			pendingForm = null;
		}

		function submitModal() {
			if (!pendingForm) return;

			// Mostrar loading antes de enviar
			const overlay = document.getElementById('loadingOverlay');
			overlay.classList.remove('hidden');
			overlay.classList.add('flex');

			const form = pendingForm;
			closeModal();
			form.submit();
		}
		// Cerrar al hacer clic en el backdrop
		document.getElementById('confirmModal').addEventListener('click', function(e) {
			if (e.target === this) closeModal();
		});

		// Cerrar con Escape
		document.addEventListener('keydown', function(e) {
			if (e.key === 'Escape') closeModal();
		});

		// Interceptar todos los formularios con data-modal
		document.querySelectorAll('form[data-modal]').forEach(function(form) {
			form.addEventListener('submit', function(e) {
				e.preventDefault();
				const type = form.dataset.modal;
				const filename = form.dataset.filename || null;
				openModal(type, form, filename);
			});
		});

		// ── Polling de semáforo ────────────────────────────────────────────────
		let wasBusy = false;

		function checkStatus() {
			fetch('?check_status=1')
				.then(r => r.json())
				.then(data => {
					const overlay = document.getElementById('loadingOverlay');
					if (data.busy) {
						overlay.classList.remove('hidden');
						overlay.classList.add('flex');
						wasBusy = true;
						setTimeout(checkStatus, 1500);
					} else {
						if (wasBusy) {
							if (data.resultado) {
								// Guardar el mensaje en sessionStorage para mostrarlo tras el reload
								sessionStorage.setItem('flash_mensaje', data.resultado);
							}
							window.location.reload();
						} else {
							setTimeout(checkStatus, 2000);
						}
					}
				})
				.catch(() => setTimeout(checkStatus, 2000));
		}

		checkStatus();

		// El formulario de "Generar Respaldo" no necesita confirmación, sólo el overlay
		document.querySelectorAll('form[data-no-modal]').forEach(function(form) {
			form.addEventListener('submit', function() {
				const overlay = document.getElementById('loadingOverlay');
				overlay.classList.remove('hidden');
				overlay.classList.add('flex');
			});
		});
	</script>
</body>

</html>