// Instrumento exclusivo de prueba: inicia el intérprete real y registra su PID.
using System;
using System.Diagnostics;
using System.IO;
using System.Text;

internal static class ContadorInterprete
{
    private static int Main(string[] args)
    {
        Console.InputEncoding = new UTF8Encoding(false);
        Console.OutputEncoding = new UTF8Encoding(false);
        var directorio = Environment.GetEnvironmentVariable("HORARIOS_CONTADOR_DIRECTORIO");
        var interprete = Environment.GetEnvironmentVariable("HORARIOS_INTERPRETE_REAL");
        if (String.IsNullOrEmpty(directorio) || String.IsNullOrEmpty(interprete) || !Directory.Exists(directorio) || !File.Exists(interprete)) return 2;
        var inicio = new ProcessStartInfo(interprete, String.Join(" ", args));
        inicio.UseShellExecute = false;
        inicio.CreateNoWindow = true;
        inicio.RedirectStandardInput = true;
        inicio.RedirectStandardOutput = true;
        inicio.RedirectStandardError = true;
        inicio.StandardOutputEncoding = new UTF8Encoding(false);
        inicio.StandardErrorEncoding = new UTF8Encoding(false);
        using (var hijo = Process.Start(inicio))
        {
            var ruta = Path.Combine(directorio, Guid.NewGuid().ToString("N") + ".inicio");
            using (var registro = new StreamWriter(new FileStream(ruta, FileMode.CreateNew, FileAccess.Write, FileShare.Read))) registro.Write(hijo.Id);
            var salida = hijo.StandardOutput.ReadToEndAsync();
            var errores = hijo.StandardError.ReadToEndAsync();
            hijo.StandardInput.Write(Console.In.ReadToEnd());
            hijo.StandardInput.Close();
            hijo.WaitForExit();
            Console.Out.Write(salida.Result);
            Console.Error.Write(errores.Result);
            return hijo.ExitCode;
        }
    }
}
