<?php
namespace App\Data;

/**
 * Preguntas del quiz de adopción tecnológica (origen: quizAdopcionData.ts del frontend).
 */
class QuizAdopcionSeedData
{
    public const COOLDOWN_DAYS = 5;

    public static function preguntas(): array
    {
        return [
            [
                'orden' => 1,
                'pregunta' => '¿Por qué se seleccionó una función polinómica de cuarto grado (n=4) en lugar de una lineal para modelar la adopción de la aplicación móvil?',
                'retroalimentacion' => 'Los polinomios de grado 4 poseen la flexibilidad matemática necesaria para cambiar de dirección y concavidad, reflejando fielmente fases de aceleración, desaceleración y estancamiento del mercado real.',
                'opciones' => [
                    ['texto' => 'Porque permite capturar cambios de concavidad y modelar fenómenos complejos como las mesetas tecnológicas.', 'correcta' => true],
                    ['texto' => 'Porque las funciones lineales solo se pueden programar en entornos de prueba beta cerrada.', 'correcta' => false],
                    ['texto' => 'Porque los servidores cloud únicamente procesan ecuaciones de grados pares.', 'correcta' => false],
                    ['texto' => 'Porque garantiza que el volumen de usuarios activos crezca de forma infinita y constante.', 'correcta' => false],
                ],
            ],
            [
                'orden' => 2,
                'pregunta' => 'En la función original U(t) = -2t⁴ + 32t³ - 180t² + 432t + 100, ¿qué representa técnicamente el término independiente 100?',
                'retroalimentacion' => 'Al evaluar la función en el tiempo de origen (t=0), todos los términos con variable se anulan, quedando únicamente el 100, lo que equivale al estado o base inicial de usuarios activos.',
                'opciones' => [
                    ['texto' => 'La cantidad máxima de peticiones HTTP que soporta la base de datos.', 'correcta' => false],
                    ['texto' => 'La base inicial de usuarios activos con la que arrancó la aplicación en el mes t=0.', 'correcta' => true],
                    ['texto' => 'El porcentaje de desaceleración del sistema durante la saturación del mercado.', 'correcta' => false],
                    ['texto' => 'El número de meses que dura el ciclo de vida de la plataforma de software.', 'correcta' => false],
                ],
            ],
            [
                'orden' => 3,
                'pregunta' => '¿Qué ocurre matemáticamente y en la infraestructura del software durante la Fase 1 (t entre 0 y 3 meses)?',
                'retroalimentacion' => 'Una primera derivada positiva indica crecimiento de usuarios, y una segunda derivada positiva indica aceleración (concavidad hacia arriba), lo que caracteriza matemáticamente la fase de viralización.',
                'opciones' => [
                    ['texto' => "U'(t) < 0 y U''(t) < 0, lo que genera una migración masiva de usuarios a otras plataformas.", 'correcta' => false],
                    ['texto' => "U'(t) > 0 y U''(t) > 0, lo que representa una fase de viralización con crecimiento acelerado.", 'correcta' => true],
                    ['texto' => "U'(t) = 0, lo que significa que el servidor web se encuentra apagado por mantenimiento.", 'correcta' => false],
                    ['texto' => 'La función se vuelve lineal debido a la baja latencia de las peticiones en Cartagena.', 'correcta' => false],
                ],
            ],
            [
                'orden' => 4,
                'pregunta' => "Si en el punto de inflexión t=3 la pendiente de la recta tangente es horizontal (U'(3)=0), ¿cómo se interpreta este comportamiento en el entorno de la aplicación?",
                'retroalimentacion' => 'Al ser la velocidad igual a cero pero mantener el crecimiento positivo en sus lados, representa una pausa o estabilización temporal en la curva de adopción antes de retomar el impulso.',
                'opciones' => [
                    ['texto' => 'Como una "meseta tecnológica" donde el ritmo de descargas se estabiliza o estanca temporalmente.', 'correcta' => true],
                    ['texto' => 'Como una caída definitiva del sistema que obliga a cambiar el lenguaje de programación.', 'correcta' => false],
                    ['texto' => 'Como el momento de máxima saturación donde se pierde el 72% de la comunidad de usuarios.', 'correcta' => false],
                    ['texto' => 'Como un crecimiento exponencial infinito inmune a los límites de la infraestructura cloud.', 'correcta' => false],
                ],
            ],
            [
                'orden' => 5,
                'pregunta' => "Durante la Fase 3 (t entre 3 y 6 meses), las condiciones son U'(t)>0 y U''(t)<0. ¿Qué le está sucediendo a la adopción de la app?",
                'retroalimentacion' => 'La velocidad es positiva (sigue creciendo) pero la aceleración es negativa (desacelera debido a la concavidad hacia abajo), indicando una saturación progresiva del mercado.',
                'opciones' => [
                    ['texto' => 'La aplicación pierde usuarios de manera acelerada y descontrolada.', 'correcta' => false],
                    ['texto' => 'El sistema sigue sumando usuarios activos, pero a un ritmo cada vez más lento (desaceleración).', 'correcta' => true],
                    ['texto' => 'El tráfico web se vuelve completamente nulo y la base de datos se vacía.', 'correcta' => false],
                    ['texto' => 'Se alcanza un estado de equilibrio estático donde ninguna variable matemática altera el servidor.', 'correcta' => false],
                ],
            ],
            [
                'orden' => 6,
                'pregunta' => '¿Qué consecuencia predice el modelo matemático para el comportamiento de la app a partir del mes t=6?',
                'retroalimentacion' => 'Al superar el punto máximo local del mes 6, la pendiente de la función se vuelve negativa, traduciéndose en una pérdida gradual de usuarios activos si no se innova la plataforma.',
                'opciones' => [
                    ['texto' => 'Un crecimiento lineal e ininterrumpido impulsado por analítica de datos.', 'correcta' => false],
                    ['texto' => "Un inicio de decrecimiento en la adopción tecnológica (U'(t) < 0).", 'correcta' => true],
                    ['texto' => 'Un reinicio automático donde la base de usuarios vuelve instantáneamente a 100.', 'correcta' => false],
                    ['texto' => 'Que la segunda derivada se volverá infinitamente positiva de manera inmediata.', 'correcta' => false],
                ],
            ],
            [
                'orden' => 7,
                'pregunta' => '¿Por qué el coeficiente principal negativo (-2t⁴) es fundamental para garantizar el realismo social y tecnológico del modelo?',
                'retroalimentacion' => 'En el largo plazo, el término de mayor grado con signo negativo domina la función, provocando un decrecimiento que emula el declive tecnológico real cuando los usuarios pierden el interés.',
                'opciones' => [
                    ['texto' => 'Porque modela el ciclo de vida natural de una tecnología que tiende a la obsolescencia si no se implementan mejoras.', 'correcta' => true],
                    ['texto' => 'Porque permite que la librería SymPy calcule derivadas sin consumir memoria en Python.', 'correcta' => false],
                    ['texto' => 'Porque fuerza a que los puntos críticos siempre sean números enteros positivos.', 'correcta' => false],
                    ['texto' => 'Porque anula la necesidad de usar el criterio de la primera derivada en el mes 3.', 'correcta' => false],
                ],
            ],
            [
                'orden' => 8,
                'pregunta' => 'En el prototipo computacional, ¿cuál es la diferencia clave entre el rol de SymPy y el de NumPy?',
                'retroalimentacion' => 'SymPy trabaja con símbolos abstractos generando fórmulas algebraicas exactas, mientras que NumPy opera con arreglos numéricos densos para computación de alto rendimiento.',
                'opciones' => [
                    ['texto' => 'SymPy se encarga de diseñar las interfaces gráficas y NumPy gestiona las consultas de la base de datos SQL.', 'correcta' => false],
                    ['texto' => 'SymPy realiza el cálculo analítico exacto de las expresiones de las derivadas, mientras que NumPy evalúa numéricamente matrices de datos de forma eficiente.', 'correcta' => true],
                    ['texto' => 'SymPy genera tablas organizadas en filas y columnas y NumPy dibuja los vectores en Matplotlib.', 'correcta' => false],
                    ['texto' => 'SymPy se utiliza únicamente cuando las derivadas fallan y arrojan un caso inconcluso.', 'correcta' => false],
                ],
            ],
            [
                'orden' => 9,
                'pregunta' => '¿Qué librería del ecosistema de Python se utilizó específicamente para construir las tablas de resultados técnicos que cruzan el tiempo con el volumen de usuarios?',
                'retroalimentacion' => 'Pandas proporciona la estructura de DataFrame, la cual está diseñada idealmente para almacenar, manipular y presentar datos tabulares en formato de filas y columnas.',
                'opciones' => [
                    ['texto' => 'Matplotlib', 'correcta' => false],
                    ['texto' => 'Pandas', 'correcta' => true],
                    ['texto' => 'SymPy', 'correcta' => false],
                    ['texto' => 'NumPy', 'correcta' => false],
                ],
            ],
            [
                'orden' => 10,
                'pregunta' => 'Si fueras el ingeniero encargado del proyecto en Cartagena, ¿para qué utilizarías la librería Matplotlib?',
                'retroalimentacion' => 'El propósito principal de Matplotlib es la visualización de datos; traduce matrices numéricas en componentes visuales (gráficas de líneas, puntos y regiones) para facilitar la toma de decisiones estratégicas.',
                'opciones' => [
                    ['texto' => 'Para compilar el código de Python en una aplicación móvil nativa de Android.', 'correcta' => false],
                    ['texto' => 'Para trazar visualmente la curva polinómica, marcar los puntos críticos (t=3, 6) y sombrear las regiones de concavidad.', 'correcta' => true],
                    ['texto' => 'Para encriptar las contraseñas de los 100 usuarios iniciales de la base de datos.', 'correcta' => false],
                    ['texto' => 'Para resolver analíticamente la ecuación de cuarto grado igualada a cero.', 'correcta' => false],
                ],
            ],
        ];
    }
}
